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
             INNER JOIN municipios m ON m.id = r.municipio_id
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
             INNER JOIN municipios m ON m.id = r.municipio_id
             INNER JOIN tipos_ajuda t ON t.id = e.tipo_ajuda_id
             {$where}
             GROUP BY t.id, t.nome, t.unidade_medida
             ORDER BY t.nome"
        );
        $this->bind($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function details(array $filters, ?int $limit = null, int $offset = 0): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $limitSql = $limit !== null ? ' LIMIT :limit OFFSET :offset' : '';
        $stmt = Database::connection()->prepare(
            "SELECT MIN(e.id) AS id,
                    COALESCE(NULLIF(f.representante_nome, ''), f.responsavel_nome) AS beneficiario_nome,
                    COALESCE(NULLIF(f.representante_cpf, ''), f.responsavel_cpf) AS beneficiario_cpf,
                    f.responsavel_nome, f.responsavel_cpf, f.representante_nome, f.representante_cpf,
                    t.id AS tipo_ajuda_id, t.nome AS tipo_ajuda_nome, t.unidade_medida,
                    COALESCE(SUM(e.quantidade), 0) AS quantidade_total,
                    COUNT(*) AS total_entregas,
                    MAX(e.data_entrega) AS ultima_entrega,
                    r.id AS residencia_id, r.protocolo, r.bairro_comunidade,
                    a.id AS acao_id, a.localidade, a.tipo_evento,
                    m.nome AS municipio_nome, m.uf,
                    GROUP_CONCAT(DISTINCT e.comprovante_codigo ORDER BY e.comprovante_codigo SEPARATOR ', ') AS comprovantes
             FROM entregas_ajuda e
             INNER JOIN familias f ON f.id = e.familia_id
             INNER JOIN residencias r ON r.id = f.residencia_id
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             INNER JOIN municipios m ON m.id = r.municipio_id
             INNER JOIN tipos_ajuda t ON t.id = e.tipo_ajuda_id
             {$where}
             GROUP BY f.id, f.responsavel_nome, f.responsavel_cpf, f.representante_nome, f.representante_cpf,
                    t.id, t.nome, t.unidade_medida,
                    r.id, r.protocolo, r.bairro_comunidade,
                    a.id, a.localidade, a.tipo_evento, m.nome, m.uf
             ORDER BY beneficiario_nome ASC, t.nome ASC, MIN(e.id) ASC{$limitSql}"
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
                    GROUP_CONCAT(DISTINCT CONCAT(t.nome, ' (', t.unidade_medida, ')') ORDER BY t.nome SEPARATOR ', ') AS tipos_materiais,
                    COUNT(DISTINCT f.id) AS total_familias,
                    COALESCE(SUM(e.quantidade), 0) AS quantidade_total
             FROM entregas_ajuda e
             INNER JOIN familias f ON f.id = e.familia_id
             INNER JOIN residencias r ON r.id = f.residencia_id
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             INNER JOIN municipios m ON m.id = r.municipio_id
             INNER JOIN tipos_ajuda t ON t.id = e.tipo_ajuda_id
             {$where}"
        );
        $this->bind($stmt, $params);
        $stmt->execute();

        $context = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($context) ? $context : [];
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
        } elseif (!empty($filters['acao_busca'])) {
            $conditions[] = '(a.localidade LIKE :acao_busca_localidade
                OR a.tipo_evento LIKE :acao_busca_evento
                OR m.nome LIKE :acao_busca_municipio)';
            $actionSearch = '%' . $filters['acao_busca'] . '%';
            $params['acao_busca_localidade'] = $actionSearch;
            $params['acao_busca_evento'] = $actionSearch;
            $params['acao_busca_municipio'] = $actionSearch;
        }

        if (!empty($filters['tipo_ajuda_id'])) {
            $conditions[] = 't.id = :tipo_ajuda_id';
            $params['tipo_ajuda_id'] = (int) $filters['tipo_ajuda_id'];
        } elseif (!empty($filters['tipo_ajuda_busca'])) {
            $conditions[] = '(t.nome LIKE :tipo_ajuda_busca_nome OR t.unidade_medida LIKE :tipo_ajuda_busca_unidade)';
            $typeSearch = '%' . $filters['tipo_ajuda_busca'] . '%';
            $params['tipo_ajuda_busca_nome'] = $typeSearch;
            $params['tipo_ajuda_busca_unidade'] = $typeSearch;
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
                OR e.comprovante_codigo LIKE :q_comprovante
                OR r.protocolo LIKE :q_protocolo)';
            $querySearch = '%' . $filters['q'] . '%';
            $params['q_beneficiario_nome'] = $querySearch;
            $params['q_beneficiario_cpf'] = $querySearch;
            $params['q_responsavel_nome'] = $querySearch;
            $params['q_responsavel_cpf'] = $querySearch;
            $params['q_comprovante'] = $querySearch;
            $params['q_protocolo'] = $querySearch;
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
