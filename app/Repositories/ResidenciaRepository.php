<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class ResidenciaRepository
{
    public function all(): array
    {
        return Database::connection()
            ->query(
                'SELECT r.id, r.protocolo, r.bairro_comunidade, r.endereco, r.quantidade_familias,
                        r.data_cadastro, a.localidade, a.tipo_evento, m.nome AS municipio_nome, m.uf,
                        u.nome AS cadastrador_nome,
                        (
                            SELECT COUNT(*)
                            FROM familias f
                            WHERE f.residencia_id = r.id AND f.deleted_at IS NULL
                        ) AS familias_cadastradas
                 FROM residencias r
                 INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
                 INNER JOIN municipios m ON m.id = r.municipio_id
                 INNER JOIN usuarios u ON u.id = r.cadastrado_por
                 WHERE r.deleted_at IS NULL
                 ORDER BY r.data_cadastro DESC'
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT r.*, a.localidade, a.tipo_evento, a.token_publico, a.status AS acao_status,
                    m.nome AS municipio_nome, m.uf, u.nome AS cadastrador_nome
             FROM residencias r
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             INNER JOIN municipios m ON m.id = r.municipio_id
             INNER JOIN usuarios u ON u.id = r.cadastrado_por
             WHERE r.id = :id AND r.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $residencia = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($residencia) ? $residencia : null;
    }

    public function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO residencias
                (acao_id, protocolo, municipio_id, bairro_comunidade, endereco, complemento,
                 latitude, longitude, quantidade_familias, cadastrado_por)
             VALUES
                (:acao_id, :protocolo, :municipio_id, :bairro_comunidade, :endereco, :complemento,
                 :latitude, :longitude, :quantidade_familias, :cadastrado_por)'
        );
        $stmt->bindValue(':acao_id', (int) $data['acao_id'], PDO::PARAM_INT);
        $stmt->bindValue(':protocolo', $data['protocolo']);
        $stmt->bindValue(':municipio_id', (int) $data['municipio_id'], PDO::PARAM_INT);
        $stmt->bindValue(':bairro_comunidade', $data['bairro_comunidade']);
        $stmt->bindValue(':endereco', $data['endereco']);
        $stmt->bindValue(':complemento', $data['complemento'] !== '' ? $data['complemento'] : null);
        $stmt->bindValue(':latitude', $data['latitude'] !== '' ? $data['latitude'] : null);
        $stmt->bindValue(':longitude', $data['longitude'] !== '' ? $data['longitude'] : null);
        $stmt->bindValue(':quantidade_familias', (int) $data['quantidade_familias'], PDO::PARAM_INT);
        $stmt->bindValue(':cadastrado_por', (int) $data['cadastrado_por'], PDO::PARAM_INT);
        $stmt->execute();

        return (int) Database::connection()->lastInsertId();
    }

    public function nextSequenceForAction(int $acaoId): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) + 1 FROM residencias WHERE acao_id = :acao_id AND deleted_at IS NULL'
        );
        $stmt->bindValue(':acao_id', $acaoId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function count(): int
    {
        return (int) Database::connection()
            ->query('SELECT COUNT(*) FROM residencias WHERE deleted_at IS NULL')
            ->fetchColumn();
    }
}
