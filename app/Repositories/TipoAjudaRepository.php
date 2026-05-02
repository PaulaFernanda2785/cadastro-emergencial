<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class TipoAjudaRepository
{
    public function all(): array
    {
        return Database::connection()
            ->query(
                'SELECT id, nome, unidade_medida, ativo, criado_em,
                        (
                            SELECT COUNT(*)
                            FROM entregas_ajuda e
                            WHERE e.tipo_ajuda_id = tipos_ajuda.id
                              AND e.deleted_at IS NULL
                        ) AS entregas_registradas
                 FROM tipos_ajuda
                 ORDER BY ativo DESC, nome'
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function active(): array
    {
        return Database::connection()
            ->query('SELECT id, nome, unidade_medida FROM tipos_ajuda WHERE ativo = 1 ORDER BY nome')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function search(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->buildSearchWhere($filters);

        $stmt = Database::connection()->prepare(
            'SELECT id, nome, unidade_medida, ativo, criado_em,
                    (
                        SELECT COUNT(*)
                        FROM entregas_ajuda e
                        WHERE e.tipo_ajuda_id = tipos_ajuda.id
                          AND e.deleted_at IS NULL
                    ) AS entregas_registradas
             FROM tipos_ajuda
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY ativo DESC, nome ASC
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
             FROM tipos_ajuda
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
            'SELECT COUNT(*) AS total_tipos,
                    COALESCE(SUM(CASE WHEN ativo = 1 THEN 1 ELSE 0 END), 0) AS ativos,
                    COALESCE(SUM(CASE WHEN ativo = 0 THEN 1 ELSE 0 END), 0) AS inativos,
                    COUNT(DISTINCT unidade_medida) AS unidades
             FROM tipos_ajuda
             WHERE ' . implode(' AND ', $where)
        );
        $this->bindSearchParams($stmt, $params);
        $stmt->execute();

        $summary = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($summary) ? $summary : [];
    }

    public function unitOptions(): array
    {
        return Database::connection()
            ->query(
                'SELECT DISTINCT unidade_medida
                 FROM tipos_ajuda
                 WHERE unidade_medida IS NOT NULL
                   AND unidade_medida <> ""
                 ORDER BY unidade_medida ASC
                 LIMIT 200'
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, nome, unidade_medida, ativo FROM tipos_ajuda WHERE id = :id LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $tipo = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($tipo) ? $tipo : null;
    }

    public function create(string $nome, string $unidadeMedida): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO tipos_ajuda (nome, unidade_medida) VALUES (:nome, :unidade_medida)'
        );
        $stmt->bindValue(':nome', $nome);
        $stmt->bindValue(':unidade_medida', $unidadeMedida);
        $stmt->execute();

        return (int) Database::connection()->lastInsertId();
    }

    public function update(int $id, string $nome, string $unidadeMedida, bool $ativo): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE tipos_ajuda
             SET nome = :nome, unidade_medida = :unidade_medida, ativo = :ativo
             WHERE id = :id'
        );
        $stmt->bindValue(':nome', $nome);
        $stmt->bindValue(':unidade_medida', $unidadeMedida);
        $stmt->bindValue(':ativo', $ativo ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function setActive(int $id, bool $active): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE tipos_ajuda
             SET ativo = :ativo
             WHERE id = :id'
        );
        $stmt->bindValue(':ativo', $active ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function countDeliveries(int $id): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM entregas_ajuda
             WHERE tipo_ajuda_id = :id
               AND deleted_at IS NULL'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM tipos_ajuda WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function countActive(): int
    {
        return (int) Database::connection()
            ->query('SELECT COUNT(*) FROM tipos_ajuda WHERE ativo = 1')
            ->fetchColumn();
    }

    private function buildSearchWhere(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (($filters['q'] ?? '') !== '') {
            $where[] = '(nome LIKE :q_nome OR unidade_medida LIKE :q_unidade)';
            $search = '%' . $filters['q'] . '%';
            $params['q_nome'] = $search;
            $params['q_unidade'] = $search;
        }

        if (($filters['status'] ?? '') === 'ativo') {
            $where[] = 'ativo = 1';
        } elseif (($filters['status'] ?? '') === 'inativo') {
            $where[] = 'ativo = 0';
        }

        if (($filters['unidade'] ?? '') !== '') {
            $where[] = 'unidade_medida LIKE :unidade';
            $params['unidade'] = '%' . $filters['unidade'] . '%';
        }

        return [$where, $params];
    }

    private function bindSearchParams(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }
}
