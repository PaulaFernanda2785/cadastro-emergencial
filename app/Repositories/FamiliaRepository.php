<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class FamiliaRepository
{
    public function all(): array
    {
        return Database::connection()
            ->query(
                'SELECT f.id, f.residencia_id, f.responsavel_nome, f.responsavel_cpf,
                        f.telefone, f.email, f.quantidade_integrantes, f.possui_gestantes, f.criado_em,
                        r.protocolo, r.bairro_comunidade,
                        a.localidade, a.tipo_evento,
                        m.nome AS municipio_nome, m.uf,
                        (
                            SELECT COUNT(*)
                            FROM entregas_ajuda e
                            WHERE e.familia_id = f.id AND e.deleted_at IS NULL
                        ) AS entregas_registradas
                 FROM familias f
                 INNER JOIN residencias r ON r.id = f.residencia_id
                 INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
                 INNER JOIN municipios m ON m.id = r.municipio_id
                 WHERE f.deleted_at IS NULL
                    AND r.deleted_at IS NULL
                    AND a.deleted_at IS NULL
                 ORDER BY f.criado_em DESC'
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT f.id, f.residencia_id, f.responsavel_nome, f.responsavel_cpf, f.responsavel_rg,
                    f.data_nascimento, f.telefone, f.email,
                    f.quantidade_integrantes, f.possui_criancas, f.possui_idosos, f.possui_pcd, f.possui_gestantes,
                    f.representante_nome, f.representante_cpf, f.representante_rg, f.representante_telefone,
                    r.protocolo, r.bairro_comunidade, r.endereco,
                    a.localidade, a.tipo_evento, m.nome AS municipio_nome, m.uf
             FROM familias f
             INNER JOIN residencias r ON r.id = f.residencia_id
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             INNER JOIN municipios m ON m.id = r.municipio_id
             WHERE f.id = :id AND f.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $familia = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($familia) ? $familia : null;
    }

    public function findForResidencia(int $id, int $residenciaId): ?array
    {
        $familia = $this->find($id);

        if ($familia === null || (int) $familia['residencia_id'] !== $residenciaId) {
            return null;
        }

        return $familia;
    }

    public function byResidencia(int $residenciaId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT f.id, f.responsavel_nome, f.responsavel_cpf, f.responsavel_rg, f.data_nascimento,
                    f.telefone, f.email, f.quantidade_integrantes, f.possui_criancas, f.possui_idosos,
                    f.possui_pcd, f.possui_gestantes, f.representante_nome, f.representante_cpf,
                    f.representante_telefone, f.criado_em,
                    (
                        SELECT COUNT(*)
                        FROM entregas_ajuda e
                        WHERE e.familia_id = f.id AND e.deleted_at IS NULL
                    ) AS entregas_registradas
             FROM familias f
             WHERE f.residencia_id = :residencia_id AND f.deleted_at IS NULL
             ORDER BY f.criado_em DESC'
        );
        $stmt->bindValue(':residencia_id', $residenciaId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countByResidencia(int $residenciaId): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM familias f
             WHERE f.residencia_id = :residencia_id
               AND f.deleted_at IS NULL'
        );
        $stmt->bindValue(':residencia_id', $residenciaId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function findCpfConflictInOpenAction(int $acaoId, array $cpfs, ?int $excludeFamiliaId = null): ?array
    {
        $normalizedCpfs = array_values(array_unique(array_filter(array_map(
            static fn (mixed $cpf): string => preg_replace('/\D+/', '', (string) $cpf) ?? '',
            $cpfs
        ))));

        if ($normalizedCpfs === []) {
            return null;
        }

        $responsavelPlaceholders = [];
        $representantePlaceholders = [];
        $params = [
            ':acao_id' => $acaoId,
            ':status_aberta' => 'aberta',
        ];

        foreach ($normalizedCpfs as $index => $cpf) {
            $responsavelKey = ':cpf_responsavel_' . $index;
            $representanteKey = ':cpf_representante_' . $index;
            $responsavelPlaceholders[] = $responsavelKey;
            $representantePlaceholders[] = $representanteKey;
            $params[$responsavelKey] = $cpf;
            $params[$representanteKey] = $cpf;
        }

        $excludeSql = '';

        if ($excludeFamiliaId !== null) {
            $excludeSql = ' AND f.id <> :exclude_familia_id';
            $params[':exclude_familia_id'] = $excludeFamiliaId;
        }

        $stmt = Database::connection()->prepare(
            'SELECT f.id, f.residencia_id, f.responsavel_nome, f.responsavel_cpf, f.representante_cpf,
                    r.protocolo
             FROM familias f
             INNER JOIN residencias r ON r.id = f.residencia_id
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             WHERE r.acao_id = :acao_id
                AND a.status = :status_aberta
                AND f.deleted_at IS NULL
                AND r.deleted_at IS NULL
                AND a.deleted_at IS NULL'
                . $excludeSql .
                ' AND (
                    REPLACE(REPLACE(REPLACE(COALESCE(f.responsavel_cpf, \'\'), \'.\', \'\'), \'-\', \'\'), \' \', \'\') IN (' . implode(', ', $responsavelPlaceholders) . ')
                    OR REPLACE(REPLACE(REPLACE(COALESCE(f.representante_cpf, \'\'), \'.\', \'\'), \'-\', \'\'), \' \', \'\') IN (' . implode(', ', $representantePlaceholders) . ')
                )
             ORDER BY f.id ASC
             LIMIT 1'
        );

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        $stmt->execute();
        $familia = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($familia) ? $familia : null;
    }

    public function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO familias
                (residencia_id, responsavel_nome, responsavel_cpf, responsavel_rg, data_nascimento,
                 telefone, email, quantidade_integrantes, possui_criancas, possui_idosos, possui_pcd, possui_gestantes,
                 representante_nome, representante_cpf, representante_rg, representante_telefone)
             VALUES
                (:residencia_id, :responsavel_nome, :responsavel_cpf, :responsavel_rg, :data_nascimento,
                 :telefone, :email, :quantidade_integrantes, :possui_criancas, :possui_idosos, :possui_pcd, :possui_gestantes,
                 :representante_nome, :representante_cpf, :representante_rg, :representante_telefone)'
        );
        $stmt->bindValue(':residencia_id', (int) $data['residencia_id'], PDO::PARAM_INT);
        $stmt->bindValue(':responsavel_nome', $data['responsavel_nome']);
        $stmt->bindValue(':responsavel_cpf', $data['responsavel_cpf']);
        $stmt->bindValue(':responsavel_rg', $data['responsavel_rg'] !== '' ? $data['responsavel_rg'] : null);
        $stmt->bindValue(':data_nascimento', $data['data_nascimento'] !== '' ? $data['data_nascimento'] : null);
        $stmt->bindValue(':telefone', $data['telefone'] !== '' ? $data['telefone'] : null);
        $stmt->bindValue(':email', $data['email'] !== '' ? $data['email'] : null);
        $stmt->bindValue(':quantidade_integrantes', (int) $data['quantidade_integrantes'], PDO::PARAM_INT);
        $stmt->bindValue(':possui_criancas', !empty($data['possui_criancas']) ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':possui_idosos', !empty($data['possui_idosos']) ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':possui_pcd', !empty($data['possui_pcd']) ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':possui_gestantes', !empty($data['possui_gestantes']) ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':representante_nome', $data['representante_nome'] !== '' ? $data['representante_nome'] : null);
        $stmt->bindValue(':representante_cpf', $data['representante_cpf'] !== '' ? $data['representante_cpf'] : null);
        $stmt->bindValue(':representante_rg', $data['representante_rg'] !== '' ? $data['representante_rg'] : null);
        $stmt->bindValue(':representante_telefone', $data['representante_telefone'] !== '' ? $data['representante_telefone'] : null);
        $stmt->execute();

        return (int) Database::connection()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE familias
             SET responsavel_nome = :responsavel_nome,
                 responsavel_cpf = :responsavel_cpf,
                 responsavel_rg = :responsavel_rg,
                 data_nascimento = :data_nascimento,
                 telefone = :telefone,
                 email = :email,
                 quantidade_integrantes = :quantidade_integrantes,
                 possui_criancas = :possui_criancas,
                 possui_idosos = :possui_idosos,
                 possui_pcd = :possui_pcd,
                 possui_gestantes = :possui_gestantes,
                 representante_nome = :representante_nome,
                 representante_cpf = :representante_cpf,
                 representante_rg = :representante_rg,
                 representante_telefone = :representante_telefone
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':responsavel_nome', $data['responsavel_nome']);
        $stmt->bindValue(':responsavel_cpf', $data['responsavel_cpf']);
        $stmt->bindValue(':responsavel_rg', $data['responsavel_rg'] !== '' ? $data['responsavel_rg'] : null);
        $stmt->bindValue(':data_nascimento', $data['data_nascimento'] !== '' ? $data['data_nascimento'] : null);
        $stmt->bindValue(':telefone', $data['telefone'] !== '' ? $data['telefone'] : null);
        $stmt->bindValue(':email', $data['email'] !== '' ? $data['email'] : null);
        $stmt->bindValue(':quantidade_integrantes', (int) $data['quantidade_integrantes'], PDO::PARAM_INT);
        $stmt->bindValue(':possui_criancas', !empty($data['possui_criancas']) ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':possui_idosos', !empty($data['possui_idosos']) ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':possui_pcd', !empty($data['possui_pcd']) ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':possui_gestantes', !empty($data['possui_gestantes']) ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':representante_nome', $data['representante_nome'] !== '' ? $data['representante_nome'] : null);
        $stmt->bindValue(':representante_cpf', $data['representante_cpf'] !== '' ? $data['representante_cpf'] : null);
        $stmt->bindValue(':representante_rg', $data['representante_rg'] !== '' ? $data['representante_rg'] : null);
        $stmt->bindValue(':representante_telefone', $data['representante_telefone'] !== '' ? $data['representante_telefone'] : null);
        $stmt->execute();
    }

    public function softDelete(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE familias SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function count(): int
    {
        return (int) Database::connection()
            ->query('SELECT COUNT(*) FROM familias WHERE deleted_at IS NULL')
            ->fetchColumn();
    }
}
