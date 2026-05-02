<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use PDOStatement;

final class DashboardRepository
{
    public function actions(?int $ownerId, ?string $activeActionToken): array
    {
        $conditions = ['a.deleted_at IS NULL'];
        $params = [];

        if ($ownerId !== null) {
            $conditions[] = 'r.cadastrado_por = :owner_id';
            $params['owner_id'] = $ownerId;
        }

        if ($activeActionToken !== null && $activeActionToken !== '') {
            $conditions[] = 'a.token_publico = :active_action_token';
            $params['active_action_token'] = $activeActionToken;
        }

        $stmt = Database::connection()->prepare(
            'SELECT DISTINCT a.id, a.localidade, a.tipo_evento, a.status,
                    m.nome AS municipio_nome, m.uf
             FROM acoes_emergenciais a
             INNER JOIN municipios m ON m.id = a.municipio_id
             LEFT JOIN residencias r ON r.acao_id = a.id AND r.deleted_at IS NULL
             WHERE ' . implode(' AND ', $conditions) . '
             ORDER BY a.criado_em DESC, a.localidade ASC
             LIMIT 500'
        );
        $this->bind($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function indicators(?int $ownerId, ?string $activeActionToken, array $filters): array
    {
        [$where, $params] = $this->residenceWhere($ownerId, $activeActionToken, $filters);

        $cadastro = $this->single(
            'SELECT COUNT(DISTINCT r.id) AS residencias,
                    COUNT(DISTINCT CASE WHEN r.latitude IS NOT NULL AND r.longitude IS NOT NULL THEN r.id END) AS georreferenciadas,
                    COUNT(DISTINCT CASE WHEN r.latitude IS NULL OR r.longitude IS NULL THEN r.id END) AS sem_georreferencia,
                    COUNT(DISTINCT f.id) AS familias,
                    COALESCE(SUM(f.quantidade_integrantes), 0) AS pessoas,
                    COUNT(DISTINCT r.bairro_comunidade) AS bairros,
                    COUNT(DISTINCT CASE WHEN f.cadastro_concluido = 1 THEN f.id END) AS cadastros_concluidos,
                    COUNT(DISTINCT CASE WHEN f.id IS NOT NULL AND f.cadastro_concluido = 0 THEN f.id END) AS cadastros_pendentes,
                    MAX(r.data_cadastro) AS ultima_atualizacao
             FROM residencias r
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             INNER JOIN municipios m ON m.id = r.municipio_id
             LEFT JOIN familias f ON f.residencia_id = r.id AND f.deleted_at IS NULL
             WHERE ' . $where,
            $params
        );

        $entrega = $this->single(
            'SELECT COUNT(*) AS entregas,
                    COUNT(DISTINCT e.familia_id) AS familias_atendidas,
                    COALESCE(SUM(e.quantidade), 0) AS quantidade_entregue
             FROM entregas_ajuda e
             INNER JOIN familias f ON f.id = e.familia_id
             INNER JOIN residencias r ON r.id = f.residencia_id
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             INNER JOIN municipios m ON m.id = r.municipio_id
             WHERE e.deleted_at IS NULL
               AND f.deleted_at IS NULL
               AND ' . $where,
            $params
        );

        return array_merge($cadastro, $entrega, [
            'acoes_abertas' => $this->openActionsCount($ownerId, $activeActionToken, $filters),
        ]);
    }

    public function conditionBreakdown(?int $ownerId, ?string $activeActionToken, array $filters): array
    {
        [$where, $params] = $this->residenceWhere($ownerId, $activeActionToken, $filters);
        $stmt = Database::connection()->prepare(
            'SELECT COALESCE(r.condicao_residencia, "sem_condicao") AS condicao,
                    COUNT(DISTINCT r.id) AS residencias,
                    COUNT(DISTINCT f.id) AS familias
             FROM residencias r
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             INNER JOIN municipios m ON m.id = r.municipio_id
             LEFT JOIN familias f ON f.residencia_id = r.id AND f.deleted_at IS NULL
             WHERE ' . $where . '
             GROUP BY COALESCE(r.condicao_residencia, "sem_condicao")
             ORDER BY residencias DESC'
        );
        $this->bind($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function neighborhoodRanking(?int $ownerId, ?string $activeActionToken, array $filters, int $limit = 8): array
    {
        [$where, $params] = $this->residenceWhere($ownerId, $activeActionToken, $filters);
        $stmt = Database::connection()->prepare(
            'SELECT r.bairro_comunidade,
                    m.nome AS municipio_nome, m.uf,
                    COUNT(DISTINCT r.id) AS residencias,
                    COUNT(DISTINCT f.id) AS familias,
                    COUNT(DISTINCT CASE WHEN r.condicao_residencia = "perda_total" THEN r.id END) AS perda_total
             FROM residencias r
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             INNER JOIN municipios m ON m.id = r.municipio_id
             LEFT JOIN familias f ON f.residencia_id = r.id AND f.deleted_at IS NULL
             WHERE ' . $where . '
             GROUP BY r.bairro_comunidade, m.nome, m.uf
             ORDER BY residencias DESC, perda_total DESC, r.bairro_comunidade ASC
             LIMIT :limit'
        );
        $this->bind($stmt, $params);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function mapResidences(?int $ownerId, ?string $activeActionToken, array $filters, int $limit = 500): array
    {
        [$where, $params] = $this->residenceWhere($ownerId, $activeActionToken, $filters);
        $stmt = Database::connection()->prepare(
            'SELECT r.id, r.protocolo, r.bairro_comunidade, r.endereco, r.imovel,
                    r.condicao_residencia, r.latitude, r.longitude, r.data_cadastro,
                    a.id AS acao_id, a.localidade, a.tipo_evento, a.status AS acao_status,
                    m.nome AS municipio_nome, m.uf,
                    (
                        SELECT COUNT(*)
                        FROM familias f_count
                        WHERE f_count.residencia_id = r.id
                          AND f_count.deleted_at IS NULL
                    ) AS familias,
                    (
                        SELECT COUNT(DISTINCT e_count.familia_id)
                        FROM familias f_count
                        INNER JOIN entregas_ajuda e_count ON e_count.familia_id = f_count.id
                        WHERE f_count.residencia_id = r.id
                          AND f_count.deleted_at IS NULL
                          AND e_count.deleted_at IS NULL
                    ) AS familias_atendidas
             FROM residencias r
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             INNER JOIN municipios m ON m.id = r.municipio_id
             WHERE ' . $where . '
               AND r.latitude IS NOT NULL
               AND r.longitude IS NOT NULL
             ORDER BY r.data_cadastro DESC
             LIMIT :limit'
        );
        $this->bind($stmt, $params);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function recentResidences(?int $ownerId, ?string $activeActionToken, array $filters, int $limit = 10): array
    {
        [$where, $params] = $this->residenceWhere($ownerId, $activeActionToken, $filters);
        $stmt = Database::connection()->prepare(
            'SELECT r.id, r.protocolo, r.bairro_comunidade, r.endereco, r.condicao_residencia,
                    r.latitude, r.longitude, r.data_cadastro,
                    a.localidade, a.tipo_evento,
                    m.nome AS municipio_nome, m.uf,
                    u.nome AS cadastrador_nome
             FROM residencias r
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             INNER JOIN municipios m ON m.id = r.municipio_id
             INNER JOIN usuarios u ON u.id = r.cadastrado_por
             WHERE ' . $where . '
             ORDER BY r.data_cadastro DESC
             LIMIT :limit'
        );
        $this->bind($stmt, $params);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function residenceWhere(?int $ownerId, ?string $activeActionToken, array $filters): array
    {
        $conditions = [
            'r.deleted_at IS NULL',
            'a.deleted_at IS NULL',
        ];
        $params = [];

        if ($ownerId !== null) {
            $conditions[] = 'r.cadastrado_por = :owner_id';
            $params['owner_id'] = $ownerId;
        }

        if ($activeActionToken !== null && $activeActionToken !== '') {
            $conditions[] = 'a.token_publico = :active_action_token';
            $params['active_action_token'] = $activeActionToken;
        }

        if (!empty($filters['acao_id'])) {
            $conditions[] = 'a.id = :acao_id';
            $params['acao_id'] = (int) $filters['acao_id'];
        } elseif (!empty($filters['acao_busca'])) {
            $conditions[] = "(CONCAT(
                COALESCE(a.localidade, ''), ' ',
                COALESCE(a.tipo_evento, ''), ' ',
                COALESCE(a.status, ''), ' ',
                COALESCE(m.nome, ''), ' ',
                COALESCE(m.uf, '')
            ) LIKE :acao_busca)";
            $params['acao_busca'] = '%' . $filters['acao_busca'] . '%';
        }

        if (!empty($filters['q'])) {
            $conditions[] = "(CONCAT(
                COALESCE(r.protocolo, ''), ' ',
                COALESCE(r.bairro_comunidade, ''), ' ',
                COALESCE(r.endereco, ''), ' ',
                COALESCE(a.localidade, ''), ' ',
                COALESCE(a.tipo_evento, ''), ' ',
                COALESCE(m.nome, ''), ' ',
                COALESCE(m.uf, '')
            ) LIKE :q)";
            $params['q'] = '%' . $filters['q'] . '%';
        }

        if (!empty($filters['condicao'])) {
            $conditions[] = 'r.condicao_residencia = :condicao';
            $params['condicao'] = $filters['condicao'];
        }

        if (!empty($filters['imovel'])) {
            $conditions[] = 'r.imovel = :imovel';
            $params['imovel'] = $filters['imovel'];
        }

        if (($filters['geo'] ?? '') === 'com_geo') {
            $conditions[] = 'r.latitude IS NOT NULL AND r.longitude IS NOT NULL';
        } elseif (($filters['geo'] ?? '') === 'sem_geo') {
            $conditions[] = '(r.latitude IS NULL OR r.longitude IS NULL)';
        }

        if (($filters['entregas'] ?? '') === 'com_entrega') {
            $conditions[] = $this->deliveryExistsSql();
        } elseif (($filters['entregas'] ?? '') === 'sem_entrega') {
            $conditions[] = 'NOT ' . $this->deliveryExistsSql();
        }

        if (($filters['cadastro'] ?? '') === 'concluido') {
            $conditions[] = $this->familyStatusExistsSql(1);
        } elseif (($filters['cadastro'] ?? '') === 'pendente') {
            $conditions[] = $this->familyStatusExistsSql(0);
        }

        if (!empty($filters['data_inicio'])) {
            $conditions[] = 'r.data_cadastro >= :data_inicio';
            $params['data_inicio'] = $filters['data_inicio'] . ' 00:00:00';
        }

        if (!empty($filters['data_fim'])) {
            $conditions[] = 'r.data_cadastro <= :data_fim';
            $params['data_fim'] = $filters['data_fim'] . ' 23:59:59';
        }

        return [implode(' AND ', $conditions), $params];
    }

    private function openActionsCount(?int $ownerId, ?string $activeActionToken, array $filters): int
    {
        if ($ownerId !== null && ($activeActionToken === null || $activeActionToken === '')) {
            return 0;
        }

        $conditions = [
            'a.deleted_at IS NULL',
            'a.status = "aberta"',
        ];
        $params = [];

        if ($activeActionToken !== null && $activeActionToken !== '') {
            $conditions[] = 'a.token_publico = :active_action_token';
            $params['active_action_token'] = $activeActionToken;
        }

        if (!empty($filters['acao_id'])) {
            $conditions[] = 'a.id = :acao_id';
            $params['acao_id'] = (int) $filters['acao_id'];
        } elseif (!empty($filters['acao_busca'])) {
            $conditions[] = "(CONCAT(
                COALESCE(a.localidade, ''), ' ',
                COALESCE(a.tipo_evento, ''), ' ',
                COALESCE(a.status, ''), ' ',
                COALESCE(m.nome, ''), ' ',
                COALESCE(m.uf, '')
            ) LIKE :acao_busca)";
            $params['acao_busca'] = '%' . $filters['acao_busca'] . '%';
        }

        $stmt = Database::connection()->prepare(
            'SELECT COUNT(DISTINCT a.id)
             FROM acoes_emergenciais a
             INNER JOIN municipios m ON m.id = a.municipio_id
             WHERE ' . implode(' AND ', $conditions)
        );
        $this->bind($stmt, $params);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    private function deliveryExistsSql(): string
    {
        return 'EXISTS (
            SELECT 1
            FROM familias f_delivery
            INNER JOIN entregas_ajuda e_delivery ON e_delivery.familia_id = f_delivery.id
            WHERE f_delivery.residencia_id = r.id
              AND f_delivery.deleted_at IS NULL
              AND e_delivery.deleted_at IS NULL
        )';
    }

    private function familyStatusExistsSql(int $status): string
    {
        return 'EXISTS (
            SELECT 1
            FROM familias f_status
            WHERE f_status.residencia_id = r.id
              AND f_status.deleted_at IS NULL
              AND f_status.cadastro_concluido = ' . $status . '
        )';
    }

    private function single(string $sql, array $params): array
    {
        $stmt = Database::connection()->prepare($sql);
        $this->bind($stmt, $params);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($result) ? $result : [];
    }

    private function bind(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }
}
