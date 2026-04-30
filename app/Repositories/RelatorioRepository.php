<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class RelatorioRepository
{
    public function indicators(array $filters): array
    {
        $cadastro = $this->single(
            'SELECT COUNT(DISTINCT r.id) AS residencias,
                    COUNT(DISTINCT f.id) AS familias,
                    COALESCE(SUM(f.quantidade_integrantes), 0) AS pessoas,
                    COUNT(DISTINCT r.bairro_comunidade) AS bairros,
                    COUNT(DISTINCT a.tipo_evento) AS tipos_evento
             FROM residencias r
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             LEFT JOIN familias f ON f.residencia_id = r.id AND f.deleted_at IS NULL
             ' . $this->cadastroWhere($filters),
            $this->cadastroParams($filters)
        );

        $entrega = $this->single(
            'SELECT COUNT(*) AS entregas,
                    COUNT(DISTINCT e.familia_id) AS familias_atendidas,
                    COALESCE(SUM(e.quantidade), 0) AS quantidade_entregue
             FROM entregas_ajuda e
             INNER JOIN familias f ON f.id = e.familia_id
             INNER JOIN residencias r ON r.id = f.residencia_id
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             ' . $this->entregaWhere($filters),
            $this->entregaParams($filters)
        );

        $pendencias = $this->single(
            'SELECT COUNT(DISTINCT f.id) AS familias_pendentes
             FROM familias f
             INNER JOIN residencias r ON r.id = f.residencia_id
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             ' . $this->cadastroWhere($filters, 'f') . '
                AND NOT EXISTS (
                    SELECT 1
                    FROM entregas_ajuda e
                    WHERE e.familia_id = f.id AND e.deleted_at IS NULL
                )',
            $this->cadastroParams($filters)
        );

        return array_merge($cadastro, $entrega, $pendencias);
    }

    public function byAction(array $filters): array
    {
        $where = $this->cadastroWhere($filters);
        $params = $this->cadastroParams($filters);
        $stmt = Database::connection()->prepare(
            "SELECT a.id, a.localidade, a.tipo_evento, a.status,
                    m.nome AS municipio_nome, m.uf,
                    COUNT(DISTINCT r.id) AS residencias,
                    COUNT(DISTINCT f.id) AS familias,
                    COALESCE(SUM(f.quantidade_integrantes), 0) AS pessoas
             FROM residencias r
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             INNER JOIN municipios m ON m.id = r.municipio_id
             LEFT JOIN familias f ON f.residencia_id = r.id AND f.deleted_at IS NULL
             {$where}
             GROUP BY a.id, a.localidade, a.tipo_evento, a.status, m.nome, m.uf
             ORDER BY m.nome, a.localidade, a.tipo_evento"
        );
        $this->bind($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function byNeighborhood(array $filters): array
    {
        $where = $this->cadastroWhere($filters);
        $params = $this->cadastroParams($filters);
        $stmt = Database::connection()->prepare(
            "SELECT r.bairro_comunidade,
                    m.nome AS municipio_nome, m.uf,
                    COUNT(DISTINCT r.id) AS residencias,
                    COUNT(DISTINCT f.id) AS familias,
                    COALESCE(SUM(f.quantidade_integrantes), 0) AS pessoas
             FROM residencias r
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             INNER JOIN municipios m ON m.id = r.municipio_id
             LEFT JOIN familias f ON f.residencia_id = r.id AND f.deleted_at IS NULL
             {$where}
             GROUP BY r.bairro_comunidade, m.nome, m.uf
             ORDER BY m.nome, r.bairro_comunidade"
        );
        $this->bind($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deliveriesByType(array $filters): array
    {
        $where = $this->entregaWhere($filters);
        $params = $this->entregaParams($filters);
        $stmt = Database::connection()->prepare(
            "SELECT t.nome, t.unidade_medida,
                    COUNT(*) AS entregas,
                    COUNT(DISTINCT e.familia_id) AS familias_atendidas,
                    COALESCE(SUM(e.quantidade), 0) AS quantidade_total
             FROM entregas_ajuda e
             INNER JOIN tipos_ajuda t ON t.id = e.tipo_ajuda_id
             INNER JOIN familias f ON f.id = e.familia_id
             INNER JOIN residencias r ON r.id = f.residencia_id
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             {$where}
             GROUP BY t.nome, t.unidade_medida
             ORDER BY t.nome"
        );
        $this->bind($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function pendingFamilies(array $filters, int $limit = 100): array
    {
        $where = $this->cadastroWhere($filters, 'f');
        $params = $this->cadastroParams($filters);
        $stmt = Database::connection()->prepare(
            "SELECT f.id, f.responsavel_nome, f.responsavel_cpf, f.telefone,
                    r.id AS residencia_id, r.protocolo, r.bairro_comunidade,
                    m.nome AS municipio_nome, m.uf,
                    a.localidade, a.tipo_evento
             FROM familias f
             INNER JOIN residencias r ON r.id = f.residencia_id
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             INNER JOIN municipios m ON m.id = r.municipio_id
             {$where}
                AND NOT EXISTS (
                    SELECT 1
                    FROM entregas_ajuda e
                    WHERE e.familia_id = f.id AND e.deleted_at IS NULL
                )
             ORDER BY r.data_cadastro DESC, f.criado_em DESC
             LIMIT {$limit}"
        );
        $this->bind($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function exportRows(array $filters): array
    {
        $where = $this->cadastroWhere($filters);
        $params = $this->cadastroParams($filters);
        $stmt = Database::connection()->prepare(
            "SELECT r.protocolo, m.nome AS municipio, m.uf, r.bairro_comunidade, r.endereco,
                    a.localidade, a.tipo_evento, r.data_cadastro,
                    f.responsavel_nome, f.responsavel_cpf, f.telefone, f.quantidade_integrantes,
                    CASE
                        WHEN EXISTS (
                            SELECT 1
                            FROM entregas_ajuda e
                            WHERE e.familia_id = f.id AND e.deleted_at IS NULL
                        ) THEN 'Atendida'
                        ELSE 'Pendente'
                    END AS status_entrega
             FROM residencias r
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             INNER JOIN municipios m ON m.id = r.municipio_id
             LEFT JOIN familias f ON f.residencia_id = r.id AND f.deleted_at IS NULL
             {$where}
             ORDER BY r.data_cadastro DESC, r.protocolo, f.responsavel_nome"
        );
        $this->bind($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function single(string $sql, array $params): array
    {
        $stmt = Database::connection()->prepare($sql);
        $this->bind($stmt, $params);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($result) ? $result : [];
    }

    private function cadastroWhere(array $filters, string $familiaAlias = 'f'): string
    {
        $conditions = [
            'r.deleted_at IS NULL',
            'a.deleted_at IS NULL',
        ];

        if ($familiaAlias !== '') {
            $conditions[] = "{$familiaAlias}.deleted_at IS NULL";
        }

        if (!empty($filters['acao_id'])) {
            $conditions[] = 'a.id = :acao_id';
        }

        if (!empty($filters['bairro'])) {
            $conditions[] = 'r.bairro_comunidade LIKE :bairro';
        }

        if (!empty($filters['data_inicio'])) {
            $conditions[] = 'r.data_cadastro >= :data_inicio';
        }

        if (!empty($filters['data_fim'])) {
            $conditions[] = 'r.data_cadastro <= :data_fim';
        }

        return 'WHERE ' . implode(' AND ', $conditions);
    }

    private function entregaWhere(array $filters): string
    {
        $conditions = [
            'e.deleted_at IS NULL',
            'f.deleted_at IS NULL',
            'r.deleted_at IS NULL',
            'a.deleted_at IS NULL',
        ];

        if (!empty($filters['acao_id'])) {
            $conditions[] = 'a.id = :acao_id';
        }

        if (!empty($filters['bairro'])) {
            $conditions[] = 'r.bairro_comunidade LIKE :bairro';
        }

        if (!empty($filters['data_inicio'])) {
            $conditions[] = 'e.data_entrega >= :data_inicio';
        }

        if (!empty($filters['data_fim'])) {
            $conditions[] = 'e.data_entrega <= :data_fim';
        }

        return 'WHERE ' . implode(' AND ', $conditions);
    }

    private function cadastroParams(array $filters): array
    {
        $params = [];

        if (!empty($filters['acao_id'])) {
            $params['acao_id'] = (int) $filters['acao_id'];
        }

        if (!empty($filters['bairro'])) {
            $params['bairro'] = '%' . $filters['bairro'] . '%';
        }

        if (!empty($filters['data_inicio'])) {
            $params['data_inicio'] = $filters['data_inicio'] . ' 00:00:00';
        }

        if (!empty($filters['data_fim'])) {
            $params['data_fim'] = $filters['data_fim'] . ' 23:59:59';
        }

        return $params;
    }

    private function entregaParams(array $filters): array
    {
        return $this->cadastroParams($filters);
    }

    private function bind(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue(':' . $key, $value, $type);
        }
    }
}
