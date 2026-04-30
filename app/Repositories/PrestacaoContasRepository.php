<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class PrestacaoContasRepository
{
    public function indicators(array $filters): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) AS total_entregas,
                    COUNT(DISTINCT e.familia_id) AS familias_atendidas,
                    COUNT(DISTINCT e.tipo_ajuda_id) AS tipos_distribuidos,
                    COALESCE(SUM(e.quantidade), 0) AS quantidade_total
             FROM entregas_ajuda e
             INNER JOIN familias f ON f.id = e.familia_id
             INNER JOIN residencias r ON r.id = f.residencia_id
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             INNER JOIN tipos_ajuda t ON t.id = e.tipo_ajuda_id
             {$where}"
        );
        $this->bind($stmt, $params);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($result) ? $result : [
            'total_entregas' => 0,
            'familias_atendidas' => 0,
            'tipos_distribuidos' => 0,
            'quantidade_total' => 0,
        ];
    }

    public function totalsByType(array $filters): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $stmt = Database::connection()->prepare(
            "SELECT t.id, t.nome, t.unidade_medida,
                    COUNT(*) AS total_entregas,
                    COUNT(DISTINCT e.familia_id) AS familias_atendidas,
                    COALESCE(SUM(e.quantidade), 0) AS quantidade_total
             FROM entregas_ajuda e
             INNER JOIN familias f ON f.id = e.familia_id
             INNER JOIN residencias r ON r.id = f.residencia_id
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             INNER JOIN tipos_ajuda t ON t.id = e.tipo_ajuda_id
             {$where}
             GROUP BY t.id, t.nome, t.unidade_medida
             ORDER BY t.nome"
        );
        $this->bind($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function details(array $filters): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $stmt = Database::connection()->prepare(
            "SELECT e.id, e.quantidade, e.data_entrega, e.comprovante_codigo,
                    f.responsavel_nome, f.responsavel_cpf,
                    t.nome AS tipo_ajuda_nome, t.unidade_medida,
                    r.protocolo, r.bairro_comunidade,
                    a.localidade, a.tipo_evento,
                    m.nome AS municipio_nome, m.uf,
                    u.nome AS entregue_por_nome
             FROM entregas_ajuda e
             INNER JOIN familias f ON f.id = e.familia_id
             INNER JOIN residencias r ON r.id = f.residencia_id
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             INNER JOIN municipios m ON m.id = r.municipio_id
             INNER JOIN tipos_ajuda t ON t.id = e.tipo_ajuda_id
             INNER JOIN usuarios u ON u.id = e.entregue_por
             {$where}
             ORDER BY e.data_entrega DESC, e.id DESC"
        );
        $this->bind($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buildWhere(array $filters): array
    {
        $conditions = [
            'e.deleted_at IS NULL',
            'f.deleted_at IS NULL',
            'r.deleted_at IS NULL',
            'a.deleted_at IS NULL',
        ];
        $params = [];

        if (!empty($filters['acao_id'])) {
            $conditions[] = 'a.id = :acao_id';
            $params['acao_id'] = (int) $filters['acao_id'];
        }

        if (!empty($filters['tipo_ajuda_id'])) {
            $conditions[] = 't.id = :tipo_ajuda_id';
            $params['tipo_ajuda_id'] = (int) $filters['tipo_ajuda_id'];
        }

        if (!empty($filters['data_inicio'])) {
            $conditions[] = 'e.data_entrega >= :data_inicio';
            $params['data_inicio'] = $filters['data_inicio'] . ' 00:00:00';
        }

        if (!empty($filters['data_fim'])) {
            $conditions[] = 'e.data_entrega <= :data_fim';
            $params['data_fim'] = $filters['data_fim'] . ' 23:59:59';
        }

        return ['WHERE ' . implode(' AND ', $conditions), $params];
    }

    private function bind(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue(':' . $key, $value, $type);
        }
    }
}
