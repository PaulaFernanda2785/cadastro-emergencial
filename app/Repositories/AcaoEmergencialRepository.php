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

    public function search(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->buildSearchWhere($filters);

        $stmt = Database::connection()->prepare(
            'SELECT a.id, a.localidade, a.tipo_evento, a.data_evento, a.token_publico, a.status,
                    a.criado_em, m.codigo_ibge, m.nome AS municipio_nome, m.uf,
                    (
                        SELECT COUNT(*)
                        FROM residencias r
                        WHERE r.acao_id = a.id AND r.deleted_at IS NULL
                    ) AS residencias_cadastradas,
                    (
                        SELECT COUNT(*)
                        FROM familias f
                        INNER JOIN residencias r ON r.id = f.residencia_id
                        WHERE r.acao_id = a.id
                          AND r.deleted_at IS NULL
                          AND f.deleted_at IS NULL
                    ) AS familias_cadastradas,
                    (
                        SELECT COUNT(DISTINCT e.familia_id)
                        FROM entregas_ajuda e
                        INNER JOIN familias f ON f.id = e.familia_id
                        INNER JOIN residencias r ON r.id = f.residencia_id
                        WHERE r.acao_id = a.id
                          AND r.deleted_at IS NULL
                          AND f.deleted_at IS NULL
                          AND e.deleted_at IS NULL
                    ) AS familias_atendidas
             FROM acoes_emergenciais a
             INNER JOIN municipios m ON m.id = a.municipio_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY a.criado_em DESC, a.id DESC
             LIMIT :limit OFFSET :offset'
        );

        $this->bindSearchParams($stmt, $params);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countSearch(array $filters): int
    {
        [$where, $params] = $this->buildSearchWhere($filters);

        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM acoes_emergenciais a
             INNER JOIN municipios m ON m.id = a.municipio_id
             WHERE ' . implode(' AND ', $where)
        );
        $this->bindSearchParams($stmt, $params);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function searchSummary(array $filters): array
    {
        [$where, $params] = $this->buildSearchWhere($filters);

        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) AS total_acoes,
                    COALESCE(SUM(CASE WHEN a.status = 'aberta' THEN 1 ELSE 0 END), 0) AS abertas,
                    COALESCE(SUM(CASE WHEN a.status = 'encerrada' THEN 1 ELSE 0 END), 0) AS encerradas,
                    COALESCE(SUM(CASE WHEN a.status = 'cancelada' THEN 1 ELSE 0 END), 0) AS canceladas,
                    COALESCE(SUM((
                        SELECT COUNT(*)
                        FROM residencias r
                        WHERE r.acao_id = a.id AND r.deleted_at IS NULL
                    )), 0) AS residencias_cadastradas,
                    COALESCE(SUM((
                        SELECT COUNT(*)
                        FROM familias f
                        INNER JOIN residencias r ON r.id = f.residencia_id
                        WHERE r.acao_id = a.id
                          AND r.deleted_at IS NULL
                          AND f.deleted_at IS NULL
                    )), 0) AS familias_cadastradas,
                    MAX(a.criado_em) AS ultima_atualizacao
             FROM acoes_emergenciais a
             INNER JOIN municipios m ON m.id = a.municipio_id
             WHERE " . implode(' AND ', $where)
        );
        $this->bindSearchParams($stmt, $params);
        $stmt->execute();

        $summary = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($summary) ? $summary : [];
    }

    public function municipalityOptions(): array
    {
        return Database::connection()
            ->query(
                'SELECT DISTINCT m.id, m.nome, m.uf
                 FROM acoes_emergenciais a
                 INNER JOIN municipios m ON m.id = a.municipio_id
                 WHERE a.deleted_at IS NULL
                 ORDER BY m.nome ASC, m.uf ASC
                 LIMIT 500'
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function eventOptions(): array
    {
        return Database::connection()
            ->query(
                'SELECT DISTINCT tipo_evento
                 FROM acoes_emergenciais
                 WHERE deleted_at IS NULL
                   AND tipo_evento IS NOT NULL
                   AND tipo_evento <> ""
                 ORDER BY tipo_evento ASC
                 LIMIT 500'
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

    public function latestOpen(): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT a.*, m.codigo_ibge, m.nome AS municipio_nome, m.uf
             FROM acoes_emergenciais a
             INNER JOIN municipios m ON m.id = a.municipio_id
             WHERE a.status = 'aberta'
               AND a.deleted_at IS NULL
             ORDER BY a.criado_em DESC, a.id DESC
             LIMIT 1"
        );
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

    private function buildSearchWhere(array $filters): array
    {
        $where = ['a.deleted_at IS NULL'];
        $params = [];

        if (($filters['q'] ?? '') !== '') {
            $where[] = '(a.localidade LIKE :q_localidade
                OR a.tipo_evento LIKE :q_evento
                OR m.nome LIKE :q_municipio
                OR m.uf LIKE :q_uf
                OR m.codigo_ibge LIKE :q_ibge)';
            $search = '%' . $filters['q'] . '%';
            $params['q_localidade'] = $search;
            $params['q_evento'] = $search;
            $params['q_municipio'] = $search;
            $params['q_uf'] = $search;
            $params['q_ibge'] = $search;
        }

        if (($filters['status'] ?? '') !== '') {
            $where[] = 'a.status = :status';
            $params['status'] = (string) $filters['status'];
        }

        if (($filters['municipio_id'] ?? '') !== '') {
            $where[] = 'm.id = :municipio_id';
            $params['municipio_id'] = (int) $filters['municipio_id'];
        } elseif (($filters['municipio_busca'] ?? '') !== '') {
            $where[] = '(m.nome LIKE :municipio_busca_nome
                OR m.uf LIKE :municipio_busca_uf
                OR m.codigo_ibge LIKE :municipio_busca_ibge)';
            $municipalitySearch = '%' . $filters['municipio_busca'] . '%';
            $params['municipio_busca_nome'] = $municipalitySearch;
            $params['municipio_busca_uf'] = $municipalitySearch;
            $params['municipio_busca_ibge'] = $municipalitySearch;
        }

        if (($filters['tipo_evento'] ?? '') !== '') {
            $where[] = 'a.tipo_evento LIKE :tipo_evento';
            $params['tipo_evento'] = '%' . $filters['tipo_evento'] . '%';
        }

        if (($filters['data_inicio'] ?? '') !== '') {
            $where[] = 'a.data_evento >= :data_inicio';
            $params['data_inicio'] = (string) $filters['data_inicio'];
        }

        if (($filters['data_fim'] ?? '') !== '') {
            $where[] = 'a.data_evento <= :data_fim';
            $params['data_fim'] = (string) $filters['data_fim'];
        }

        return [$where, $params];
    }

    private function bindSearchParams(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
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

    public function updateStatus(int $id, string $status): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE acoes_emergenciais
             SET status = :status
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function countActiveRecords(int $id): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM residencias r
             WHERE r.acao_id = :id
               AND r.deleted_at IS NULL'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function softDelete(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE acoes_emergenciais
             SET deleted_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );
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
