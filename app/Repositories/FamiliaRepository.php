<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class FamiliaRepository
{
    public function all(?int $cadastradoPor = null): array
    {
        $sql = 'SELECT f.id, f.residencia_id, f.responsavel_nome, f.responsavel_cpf,
                       f.responsavel_rg, f.responsavel_sexo, f.responsavel_orgao_expedidor,
                       f.telefone, f.email, f.quantidade_integrantes, f.possui_gestantes,
                       f.renda_familiar, f.situacao_familia, f.recebe_beneficio_social,
                       f.cadastro_concluido, f.criado_em,
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
                   AND a.deleted_at IS NULL';

        if ($cadastradoPor !== null) {
            $sql .= ' AND r.cadastrado_por = :cadastrado_por';
        }

        $sql .= ' ORDER BY f.criado_em DESC';

        $stmt = Database::connection()->prepare($sql);

        if ($cadastradoPor !== null) {
            $stmt->bindValue(':cadastrado_por', $cadastradoPor, PDO::PARAM_INT);
        }

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT f.id, f.residencia_id, f.responsavel_nome, f.responsavel_cpf, f.responsavel_rg,
                    f.responsavel_sexo, f.responsavel_orgao_expedidor,
                    f.data_nascimento, f.telefone, f.email,
                    f.quantidade_integrantes, f.possui_criancas, f.possui_idosos, f.possui_pcd, f.possui_gestantes,
                    f.renda_familiar, f.perdas_bens_moveis, f.situacao_familia,
                    f.recebe_beneficio_social, f.beneficio_social_nome,
                    f.cadastro_concluido, f.conclusao_observacoes,
                    f.representante_nome, f.representante_cpf, f.representante_rg,
                    f.representante_orgao_expedidor, f.representante_data_nascimento,
                    f.representante_sexo, f.representante_telefone,
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
            'SELECT f.id, f.responsavel_nome, f.responsavel_cpf, f.responsavel_rg,
                    f.responsavel_sexo, f.responsavel_orgao_expedidor, f.data_nascimento,
                    f.telefone, f.email, f.quantidade_integrantes, f.possui_criancas, f.possui_idosos,
                    f.possui_pcd, f.possui_gestantes, f.renda_familiar, f.perdas_bens_moveis,
                    f.situacao_familia, f.recebe_beneficio_social, f.beneficio_social_nome,
                    f.cadastro_concluido, f.conclusao_observacoes,
                    f.representante_nome, f.representante_cpf, f.representante_rg,
                    f.representante_orgao_expedidor, f.representante_data_nascimento,
                    f.representante_sexo, f.representante_telefone, f.criado_em,
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
                (residencia_id, responsavel_nome, responsavel_cpf, responsavel_rg, responsavel_sexo,
                 responsavel_orgao_expedidor, data_nascimento, telefone, email, quantidade_integrantes,
                 possui_criancas, possui_idosos, possui_pcd, possui_gestantes, renda_familiar,
                 perdas_bens_moveis, situacao_familia, recebe_beneficio_social, beneficio_social_nome,
                 cadastro_concluido, conclusao_observacoes,
                 representante_nome, representante_cpf, representante_rg, representante_orgao_expedidor,
                 representante_data_nascimento, representante_sexo, representante_telefone)
             VALUES
                (:residencia_id, :responsavel_nome, :responsavel_cpf, :responsavel_rg, :responsavel_sexo,
                 :responsavel_orgao_expedidor, :data_nascimento, :telefone, :email, :quantidade_integrantes,
                 :possui_criancas, :possui_idosos, :possui_pcd, :possui_gestantes, :renda_familiar,
                 :perdas_bens_moveis, :situacao_familia, :recebe_beneficio_social, :beneficio_social_nome,
                 :cadastro_concluido, :conclusao_observacoes,
                 :representante_nome, :representante_cpf, :representante_rg, :representante_orgao_expedidor,
                 :representante_data_nascimento, :representante_sexo, :representante_telefone)'
        );
        $stmt->bindValue(':residencia_id', (int) $data['residencia_id'], PDO::PARAM_INT);
        $stmt->bindValue(':responsavel_nome', $data['responsavel_nome']);
        $stmt->bindValue(':responsavel_cpf', $data['responsavel_cpf']);
        $stmt->bindValue(':responsavel_rg', $data['responsavel_rg'] !== '' ? $data['responsavel_rg'] : null);
        $stmt->bindValue(':responsavel_sexo', $data['responsavel_sexo'] !== '' ? $data['responsavel_sexo'] : null);
        $stmt->bindValue(':responsavel_orgao_expedidor', $data['responsavel_orgao_expedidor'] !== '' ? $data['responsavel_orgao_expedidor'] : null);
        $stmt->bindValue(':data_nascimento', $data['data_nascimento'] !== '' ? $data['data_nascimento'] : null);
        $stmt->bindValue(':telefone', $data['telefone'] !== '' ? $data['telefone'] : null);
        $stmt->bindValue(':email', $data['email'] !== '' ? $data['email'] : null);
        $stmt->bindValue(':quantidade_integrantes', (int) $data['quantidade_integrantes'], PDO::PARAM_INT);
        $stmt->bindValue(':possui_criancas', !empty($data['possui_criancas']) ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':possui_idosos', !empty($data['possui_idosos']) ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':possui_pcd', !empty($data['possui_pcd']) ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':possui_gestantes', !empty($data['possui_gestantes']) ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':renda_familiar', $data['renda_familiar'] !== '' ? $data['renda_familiar'] : null);
        $stmt->bindValue(':perdas_bens_moveis', $data['perdas_bens_moveis'] !== '' ? $data['perdas_bens_moveis'] : null);
        $stmt->bindValue(':situacao_familia', $data['situacao_familia'] !== '' ? $data['situacao_familia'] : null);
        $stmt->bindValue(':recebe_beneficio_social', !empty($data['recebe_beneficio_social']) ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':beneficio_social_nome', $data['beneficio_social_nome'] !== '' ? $data['beneficio_social_nome'] : null);
        $stmt->bindValue(':cadastro_concluido', !empty($data['cadastro_concluido']) ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':conclusao_observacoes', $data['conclusao_observacoes'] !== '' ? $data['conclusao_observacoes'] : null);
        $stmt->bindValue(':representante_nome', $data['representante_nome'] !== '' ? $data['representante_nome'] : null);
        $stmt->bindValue(':representante_cpf', $data['representante_cpf'] !== '' ? $data['representante_cpf'] : null);
        $stmt->bindValue(':representante_rg', $data['representante_rg'] !== '' ? $data['representante_rg'] : null);
        $stmt->bindValue(':representante_orgao_expedidor', $data['representante_orgao_expedidor'] !== '' ? $data['representante_orgao_expedidor'] : null);
        $stmt->bindValue(':representante_data_nascimento', $data['representante_data_nascimento'] !== '' ? $data['representante_data_nascimento'] : null);
        $stmt->bindValue(':representante_sexo', $data['representante_sexo'] !== '' ? $data['representante_sexo'] : null);
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
                 responsavel_sexo = :responsavel_sexo,
                 responsavel_orgao_expedidor = :responsavel_orgao_expedidor,
                 data_nascimento = :data_nascimento,
                 telefone = :telefone,
                 email = :email,
                 quantidade_integrantes = :quantidade_integrantes,
                 possui_criancas = :possui_criancas,
                 possui_idosos = :possui_idosos,
                 possui_pcd = :possui_pcd,
                 possui_gestantes = :possui_gestantes,
                 renda_familiar = :renda_familiar,
                 perdas_bens_moveis = :perdas_bens_moveis,
                 situacao_familia = :situacao_familia,
                 recebe_beneficio_social = :recebe_beneficio_social,
                 beneficio_social_nome = :beneficio_social_nome,
                 cadastro_concluido = :cadastro_concluido,
                 conclusao_observacoes = :conclusao_observacoes,
                 representante_nome = :representante_nome,
                 representante_cpf = :representante_cpf,
                 representante_rg = :representante_rg,
                 representante_orgao_expedidor = :representante_orgao_expedidor,
                 representante_data_nascimento = :representante_data_nascimento,
                 representante_sexo = :representante_sexo,
                 representante_telefone = :representante_telefone
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':responsavel_nome', $data['responsavel_nome']);
        $stmt->bindValue(':responsavel_cpf', $data['responsavel_cpf']);
        $stmt->bindValue(':responsavel_rg', $data['responsavel_rg'] !== '' ? $data['responsavel_rg'] : null);
        $stmt->bindValue(':responsavel_sexo', $data['responsavel_sexo'] !== '' ? $data['responsavel_sexo'] : null);
        $stmt->bindValue(':responsavel_orgao_expedidor', $data['responsavel_orgao_expedidor'] !== '' ? $data['responsavel_orgao_expedidor'] : null);
        $stmt->bindValue(':data_nascimento', $data['data_nascimento'] !== '' ? $data['data_nascimento'] : null);
        $stmt->bindValue(':telefone', $data['telefone'] !== '' ? $data['telefone'] : null);
        $stmt->bindValue(':email', $data['email'] !== '' ? $data['email'] : null);
        $stmt->bindValue(':quantidade_integrantes', (int) $data['quantidade_integrantes'], PDO::PARAM_INT);
        $stmt->bindValue(':possui_criancas', !empty($data['possui_criancas']) ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':possui_idosos', !empty($data['possui_idosos']) ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':possui_pcd', !empty($data['possui_pcd']) ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':possui_gestantes', !empty($data['possui_gestantes']) ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':renda_familiar', $data['renda_familiar'] !== '' ? $data['renda_familiar'] : null);
        $stmt->bindValue(':perdas_bens_moveis', $data['perdas_bens_moveis'] !== '' ? $data['perdas_bens_moveis'] : null);
        $stmt->bindValue(':situacao_familia', $data['situacao_familia'] !== '' ? $data['situacao_familia'] : null);
        $stmt->bindValue(':recebe_beneficio_social', !empty($data['recebe_beneficio_social']) ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':beneficio_social_nome', $data['beneficio_social_nome'] !== '' ? $data['beneficio_social_nome'] : null);
        $stmt->bindValue(':cadastro_concluido', !empty($data['cadastro_concluido']) ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':conclusao_observacoes', $data['conclusao_observacoes'] !== '' ? $data['conclusao_observacoes'] : null);
        $stmt->bindValue(':representante_nome', $data['representante_nome'] !== '' ? $data['representante_nome'] : null);
        $stmt->bindValue(':representante_cpf', $data['representante_cpf'] !== '' ? $data['representante_cpf'] : null);
        $stmt->bindValue(':representante_rg', $data['representante_rg'] !== '' ? $data['representante_rg'] : null);
        $stmt->bindValue(':representante_orgao_expedidor', $data['representante_orgao_expedidor'] !== '' ? $data['representante_orgao_expedidor'] : null);
        $stmt->bindValue(':representante_data_nascimento', $data['representante_data_nascimento'] !== '' ? $data['representante_data_nascimento'] : null);
        $stmt->bindValue(':representante_sexo', $data['representante_sexo'] !== '' ? $data['representante_sexo'] : null);
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
