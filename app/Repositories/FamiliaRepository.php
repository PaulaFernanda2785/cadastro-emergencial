<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class FamiliaRepository
{
    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT f.id, f.residencia_id, f.responsavel_nome, f.responsavel_cpf, f.telefone,
                    f.quantidade_integrantes, f.possui_criancas, f.possui_idosos, f.possui_pcd,
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

    public function byResidencia(int $residenciaId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT f.id, f.responsavel_nome, f.responsavel_cpf, f.responsavel_rg, f.data_nascimento,
                    f.telefone, f.email, f.quantidade_integrantes, f.possui_criancas, f.possui_idosos,
                    f.possui_pcd, f.representante_nome, f.representante_cpf, f.representante_telefone, f.criado_em,
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

    public function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO familias
                (residencia_id, responsavel_nome, responsavel_cpf, responsavel_rg, data_nascimento,
                 telefone, email, quantidade_integrantes, possui_criancas, possui_idosos, possui_pcd,
                 representante_nome, representante_cpf, representante_rg, representante_telefone)
             VALUES
                (:residencia_id, :responsavel_nome, :responsavel_cpf, :responsavel_rg, :data_nascimento,
                 :telefone, :email, :quantidade_integrantes, :possui_criancas, :possui_idosos, :possui_pcd,
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
        $stmt->bindValue(':representante_nome', $data['representante_nome'] !== '' ? $data['representante_nome'] : null);
        $stmt->bindValue(':representante_cpf', $data['representante_cpf'] !== '' ? $data['representante_cpf'] : null);
        $stmt->bindValue(':representante_rg', $data['representante_rg'] !== '' ? $data['representante_rg'] : null);
        $stmt->bindValue(':representante_telefone', $data['representante_telefone'] !== '' ? $data['representante_telefone'] : null);
        $stmt->execute();

        return (int) Database::connection()->lastInsertId();
    }

    public function count(): int
    {
        return (int) Database::connection()
            ->query('SELECT COUNT(*) FROM familias WHERE deleted_at IS NULL')
            ->fetchColumn();
    }
}
