<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class AcaoEmergencialRepository
{
    public function all(): array
    {
        return Database::connection()
            ->query(
                'SELECT a.id, a.localidade, a.tipo_evento, a.data_evento, a.token_publico, a.status,
                        a.criado_em, m.codigo_ibge, m.nome AS municipio_nome, m.uf
                 FROM acoes_emergenciais a
                 INNER JOIN municipios m ON m.id = a.municipio_id
                 WHERE a.deleted_at IS NULL
                 ORDER BY a.criado_em DESC'
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT a.*, m.codigo_ibge, m.nome AS municipio_nome, m.uf
             FROM acoes_emergenciais a
             INNER JOIN municipios m ON m.id = a.municipio_id
             WHERE a.id = :id AND a.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $acao = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($acao) ? $acao : null;
    }

    public function findByPublicToken(string $token): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT a.*, m.codigo_ibge, m.nome AS municipio_nome, m.uf
             FROM acoes_emergenciais a
             INNER JOIN municipios m ON m.id = a.municipio_id
             WHERE a.token_publico = :token AND a.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->bindValue(':token', $token);
        $stmt->execute();

        $acao = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($acao) ? $acao : null;
    }

    public function localitiesByMunicipalityCode(): array
    {
        $rows = Database::connection()
            ->query(
                'SELECT m.codigo_ibge, a.localidade AS nome
                 FROM acoes_emergenciais a
                 INNER JOIN municipios m ON m.id = a.municipio_id
                 WHERE a.deleted_at IS NULL
                    AND a.localidade IS NOT NULL
                    AND a.localidade <> ""
                 UNION
                 SELECT m.codigo_ibge, r.bairro_comunidade AS nome
                 FROM residencias r
                 INNER JOIN municipios m ON m.id = r.municipio_id
                 WHERE r.deleted_at IS NULL
                    AND r.bairro_comunidade IS NOT NULL
                    AND r.bairro_comunidade <> ""
                 ORDER BY codigo_ibge, nome'
            )
            ->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];

        foreach ($rows as $row) {
            $code = (string) $row['codigo_ibge'];
            $grouped[$code][] = (string) $row['nome'];
        }

        return $grouped;
    }

    public function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO acoes_emergenciais
                (municipio_id, localidade, tipo_evento, data_evento, token_publico, status, criado_por)
             VALUES
                (:municipio_id, :localidade, :tipo_evento, :data_evento, :token_publico, :status, :criado_por)'
        );
        $stmt->bindValue(':municipio_id', (int) $data['municipio_id'], PDO::PARAM_INT);
        $stmt->bindValue(':localidade', $data['localidade']);
        $stmt->bindValue(':tipo_evento', $data['tipo_evento']);
        $stmt->bindValue(':data_evento', $data['data_evento']);
        $stmt->bindValue(':token_publico', $data['token_publico']);
        $stmt->bindValue(':status', $data['status']);
        $stmt->bindValue(':criado_por', (int) $data['criado_por'], PDO::PARAM_INT);
        $stmt->execute();

        return (int) Database::connection()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE acoes_emergenciais
             SET municipio_id = :municipio_id,
                 localidade = :localidade,
                 tipo_evento = :tipo_evento,
                 data_evento = :data_evento,
                 status = :status
             WHERE id = :id'
        );
        $stmt->bindValue(':municipio_id', (int) $data['municipio_id'], PDO::PARAM_INT);
        $stmt->bindValue(':localidade', $data['localidade']);
        $stmt->bindValue(':tipo_evento', $data['tipo_evento']);
        $stmt->bindValue(':data_evento', $data['data_evento']);
        $stmt->bindValue(':status', $data['status']);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function countOpen(): int
    {
        return (int) Database::connection()
            ->query("SELECT COUNT(*) FROM acoes_emergenciais WHERE status = 'aberta' AND deleted_at IS NULL")
            ->fetchColumn();
    }
}
