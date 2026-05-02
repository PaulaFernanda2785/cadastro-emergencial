<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class RecomecarRepository
{
    public function indicators(array $filters): array
    {
        [$where, $params] = $this->buildWhere($filters, false);
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) AS total_familias,
                    COALESCE(SUM(CASE WHEN " . $this->eligibleSql() . " THEN 1 ELSE 0 END), 0) AS familias_aptas,
                    COALESCE(SUM(CASE WHEN " . $this->eligibleSql() . " THEN 0 ELSE 1 END), 0) AS familias_inaptas
             FROM familias f
             INNER JOIN residencias r ON r.id = f.residencia_id
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             INNER JOIN municipios m ON m.id = r.municipio_id
             {$where}"
        );
        $this->bind($stmt, $params);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($result) ? $result : [
            'total_familias' => 0,
            'familias_aptas' => 0,
            'familias_inaptas' => 0,
        ];
    }

    public function countDetails(array $filters): int
    {
        [$where, $params] = $this->buildWhere($filters);
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*)
             FROM familias f
             INNER JOIN residencias r ON r.id = f.residencia_id
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             INNER JOIN municipios m ON m.id = r.municipio_id
             {$where}"
        );
        $this->bind($stmt, $params);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function details(array $filters, ?int $limit = null, int $offset = 0): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $limitSql = $limit !== null ? ' LIMIT :limit OFFSET :offset' : '';
        $stmt = Database::connection()->prepare(
            "SELECT f.id AS familia_id,
                    COALESCE(NULLIF(f.representante_nome, ''), f.responsavel_nome) AS beneficiario_nome,
                    COALESCE(NULLIF(f.representante_cpf, ''), f.responsavel_cpf) AS beneficiario_cpf,
                    COALESCE(NULLIF(f.representante_rg, ''), f.responsavel_rg) AS beneficiario_rg,
                    COALESCE(NULLIF(f.representante_orgao_expedidor, ''), f.responsavel_orgao_expedidor) AS beneficiario_orgao_expedidor,
                    COALESCE(NULLIF(f.representante_sexo, ''), f.responsavel_sexo) AS beneficiario_sexo,
                    COALESCE(f.representante_data_nascimento, f.data_nascimento) AS beneficiario_data_nascimento,
                    f.responsavel_nome,
                    f.responsavel_cpf,
                    f.representante_nome,
                    f.representante_cpf,
                    f.renda_familiar,
                    r.id AS residencia_id,
                    r.protocolo,
                    r.bairro_comunidade,
                    r.condicao_residencia,
                    a.id AS acao_id,
                    a.localidade,
                    a.tipo_evento,
                    m.nome AS municipio_nome,
                    m.uf,
                    (
                        SELECT COUNT(*)
                        FROM entregas_ajuda e
                        WHERE e.familia_id = f.id
                          AND e.deleted_at IS NULL
                    ) AS total_entregas,
                    (
                        SELECT MAX(e.data_entrega)
                        FROM entregas_ajuda e
                        WHERE e.familia_id = f.id
                          AND e.deleted_at IS NULL
                    ) AS ultima_entrega,
                    CASE WHEN EXISTS (
                        SELECT 1
                        FROM entregas_ajuda e
                        WHERE e.familia_id = f.id
                          AND e.deleted_at IS NULL
                    ) THEN 'entregue' ELSE 'nao_entregue' END AS status_entrega,
                    CASE WHEN " . $this->eligibleSql() . " THEN 'apta' ELSE 'inapta' END AS aptidao,
                    CASE
                        WHEN r.condicao_residencia = 'nao_atingida' THEN 'Imovel nao atingido'
                        WHEN f.renda_familiar = 'acima_3_salarios' THEN 'Renda familiar acima de 3 salarios'
                        ELSE ''
                    END AS motivo_inaptidao
             FROM familias f
             INNER JOIN residencias r ON r.id = f.residencia_id
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             INNER JOIN municipios m ON m.id = r.municipio_id
             {$where}
             ORDER BY beneficiario_nome ASC, f.id ASC{$limitSql}"
        );
        $this->bind($stmt, $params);

        if ($limit !== null) {
            $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
            $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        }

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function documentContext(array $filters): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $stmt = Database::connection()->prepare(
            "SELECT GROUP_CONCAT(DISTINCT CONCAT(m.nome, '/', m.uf) ORDER BY m.nome, m.uf SEPARATOR ', ') AS municipios,
                    GROUP_CONCAT(DISTINCT a.localidade ORDER BY a.localidade SEPARATOR ', ') AS localidades,
                    GROUP_CONCAT(DISTINCT r.bairro_comunidade ORDER BY r.bairro_comunidade SEPARATOR ', ') AS bairros,
                    COUNT(*) AS total_familias,
                    COALESCE(SUM(CASE WHEN " . $this->eligibleSql() . " THEN 1 ELSE 0 END), 0) AS familias_aptas,
                    COALESCE(SUM(CASE WHEN " . $this->eligibleSql() . " THEN 0 ELSE 1 END), 0) AS familias_inaptas
             FROM familias f
             INNER JOIN residencias r ON r.id = f.residencia_id
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             INNER JOIN municipios m ON m.id = r.municipio_id
             {$where}"
        );
        $this->bind($stmt, $params);
        $stmt->execute();

        $context = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($context) ? $context : [];
    }

    private function buildWhere(array $filters, bool $applyEligibility = true): array
    {
        $conditions = [
            'f.deleted_at IS NULL',
            'r.deleted_at IS NULL',
            'a.deleted_at IS NULL',
        ];
        $params = [];

        if (!empty($filters['acao_id'])) {
            $conditions[] = 'a.id = :acao_id';
            $params['acao_id'] = (int) $filters['acao_id'];
        } elseif (!empty($filters['acao_busca'])) {
            $conditions[] = '(a.localidade LIKE :acao_busca_localidade
                OR a.tipo_evento LIKE :acao_busca_evento
                OR m.nome LIKE :acao_busca_municipio)';
            $actionSearch = '%' . $filters['acao_busca'] . '%';
            $params['acao_busca_localidade'] = $actionSearch;
            $params['acao_busca_evento'] = $actionSearch;
            $params['acao_busca_municipio'] = $actionSearch;
        }

        if (!empty($filters['localidade_busca'])) {
            $conditions[] = '(a.localidade LIKE :localidade_busca_acao OR r.bairro_comunidade LIKE :localidade_busca_bairro)';
            $locationSearch = '%' . $filters['localidade_busca'] . '%';
            $params['localidade_busca_acao'] = $locationSearch;
            $params['localidade_busca_bairro'] = $locationSearch;
        }

        if (!empty($filters['q'])) {
            $conditions[] = '(COALESCE(NULLIF(f.representante_nome, \'\'), f.responsavel_nome) LIKE :q_beneficiario_nome
                OR COALESCE(NULLIF(f.representante_cpf, \'\'), f.responsavel_cpf) LIKE :q_beneficiario_cpf
                OR f.responsavel_nome LIKE :q_responsavel_nome
                OR f.responsavel_cpf LIKE :q_responsavel_cpf
                OR r.protocolo LIKE :q_protocolo)';
            $querySearch = '%' . $filters['q'] . '%';
            $params['q_beneficiario_nome'] = $querySearch;
            $params['q_beneficiario_cpf'] = $querySearch;
            $params['q_responsavel_nome'] = $querySearch;
            $params['q_responsavel_cpf'] = $querySearch;
            $params['q_protocolo'] = $querySearch;
        }

        if (!empty($filters['data_inicio'])) {
            $conditions[] = 'r.data_cadastro >= :data_inicio';
            $params['data_inicio'] = $filters['data_inicio'] . ' 00:00:00';
        }

        if (!empty($filters['data_fim'])) {
            $conditions[] = 'r.data_cadastro <= :data_fim';
            $params['data_fim'] = $filters['data_fim'] . ' 23:59:59';
        }

        if (($filters['status_entrega'] ?? '') === 'entregue') {
            $conditions[] = 'EXISTS (
                SELECT 1
                FROM entregas_ajuda e_status
                WHERE e_status.familia_id = f.id
                  AND e_status.deleted_at IS NULL
            )';
        } elseif (($filters['status_entrega'] ?? '') === 'nao_entregue') {
            $conditions[] = 'NOT EXISTS (
                SELECT 1
                FROM entregas_ajuda e_status
                WHERE e_status.familia_id = f.id
                  AND e_status.deleted_at IS NULL
            )';
        }

        if ($applyEligibility) {
            if (($filters['aptidao'] ?? 'apta') === 'apta') {
                $conditions[] = $this->eligibleSql();
            } elseif (($filters['aptidao'] ?? '') === 'inapta') {
                $conditions[] = 'NOT (' . $this->eligibleSql() . ')';
            }
        }

        return ['WHERE ' . implode(' AND ', $conditions), $params];
    }

    private function eligibleSql(): string
    {
        return "(r.condicao_residencia IS NULL OR r.condicao_residencia <> 'nao_atingida')
            AND (f.renda_familiar IS NULL OR f.renda_familiar <> 'acima_3_salarios')";
    }

    private function bind(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue(':' . $key, $value, $type);
        }
    }
}
