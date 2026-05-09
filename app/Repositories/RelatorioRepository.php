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
                    COUNT(DISTINCT a.tipo_evento) AS tipos_evento,
                    COUNT(DISTINCT CASE WHEN f.cadastro_concluido = 1 THEN f.id END) AS cadastros_concluidos,
                    COUNT(DISTINCT CASE WHEN f.id IS NOT NULL AND f.cadastro_concluido = 0 THEN f.id END) AS cadastros_pendentes,
                    COUNT(DISTINCT CASE WHEN r.latitude IS NOT NULL AND r.longitude IS NOT NULL THEN r.id END) AS residencias_georreferenciadas,
                    COUNT(DISTINCT CASE WHEN r.foto_georreferenciada IS NOT NULL AND r.foto_georreferenciada <> "" THEN r.id END) AS residencias_com_foto
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
                    WHERE e.familia_id = f.id
                      AND COALESCE(e.status_operacional, "entregue") = "entregue"
                      AND e.deleted_at IS NULL
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

    public function byHousingType(array $filters): array
    {
        $where = $this->cadastroWhere($filters);
        $params = $this->cadastroParams($filters);
        $stmt = Database::connection()->prepare(
            "SELECT r.imovel,
                    COUNT(DISTINCT r.id) AS residencias,
                    COUNT(DISTINCT f.id) AS familias,
                    COALESCE(SUM(f.quantidade_integrantes), 0) AS pessoas
             FROM residencias r
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             LEFT JOIN familias f ON f.residencia_id = r.id AND f.deleted_at IS NULL
             {$where}
             GROUP BY r.imovel
             ORDER BY r.imovel"
        );
        $this->bind($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function byResidenceCondition(array $filters): array
    {
        $where = $this->cadastroWhere($filters);
        $params = $this->cadastroParams($filters);
        $stmt = Database::connection()->prepare(
            "SELECT r.condicao_residencia,
                    COUNT(DISTINCT r.id) AS residencias,
                    COUNT(DISTINCT f.id) AS familias,
                    COALESCE(SUM(f.quantidade_integrantes), 0) AS pessoas
             FROM residencias r
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             LEFT JOIN familias f ON f.residencia_id = r.id AND f.deleted_at IS NULL
             {$where}
             GROUP BY r.condicao_residencia
             ORDER BY r.condicao_residencia"
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

    public function vulnerableGroups(array $filters): array
    {
        $stats = $this->single(
            'SELECT COUNT(DISTINCT CASE WHEN f.possui_criancas = 1 THEN f.id END) AS criancas,
                    COUNT(DISTINCT CASE WHEN f.possui_idosos = 1 THEN f.id END) AS idosos,
                    COUNT(DISTINCT CASE WHEN f.possui_pcd = 1 THEN f.id END) AS pcd,
                    COUNT(DISTINCT CASE WHEN f.possui_gestantes = 1 THEN f.id END) AS gestantes,
                    COUNT(DISTINCT CASE WHEN f.recebe_beneficio_social = 1 THEN f.id END) AS beneficio_social
             FROM familias f
             INNER JOIN residencias r ON r.id = f.residencia_id
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             ' . $this->cadastroWhere($filters, 'f'),
            $this->cadastroParams($filters)
        );

        return array_map(
            static fn (string $key): array => ['grupo' => $key, 'familias' => (int) ($stats[$key] ?? 0)],
            ['criancas', 'idosos', 'pcd', 'gestantes', 'beneficio_social']
        );
    }

    public function registrationQuality(array $filters): array
    {
        return $this->single(
            'SELECT COUNT(DISTINCT r.id) AS residencias,
                    COUNT(DISTINCT CASE WHEN r.foto_georreferenciada IS NOT NULL AND r.foto_georreferenciada <> "" THEN r.id END) AS com_foto,
                    COUNT(DISTINCT CASE WHEN r.latitude IS NOT NULL AND r.longitude IS NOT NULL THEN r.id END) AS com_geolocalizacao,
                    COUNT(DISTINCT f.id) AS familias,
                    COUNT(DISTINCT CASE WHEN f.cadastro_concluido = 1 THEN f.id END) AS familias_concluidas,
                    COUNT(DISTINCT CASE WHEN f.id IS NOT NULL AND f.cadastro_concluido = 0 THEN f.id END) AS familias_pendentes,
                    COUNT(DISTINCT CASE WHEN f.email IS NOT NULL AND f.email <> "" THEN f.id END) AS familias_com_email,
                    COUNT(DISTINCT CASE WHEN f.telefone IS NOT NULL AND f.telefone <> "" THEN f.id END) AS familias_com_telefone
             FROM residencias r
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             LEFT JOIN familias f ON f.residencia_id = r.id AND f.deleted_at IS NULL
             ' . $this->cadastroWhere($filters),
            $this->cadastroParams($filters)
        );
    }

    public function deliveryTimeline(array $filters): array
    {
        $where = $this->entregaWhere($filters);
        $params = $this->entregaParams($filters);
        $stmt = Database::connection()->prepare(
            "SELECT DATE(e.data_entrega) AS data,
                    COUNT(*) AS entregas,
                    COUNT(DISTINCT e.familia_id) AS familias_atendidas,
                    COALESCE(SUM(e.quantidade), 0) AS quantidade_total
             FROM entregas_ajuda e
             INNER JOIN familias f ON f.id = e.familia_id
             INNER JOIN residencias r ON r.id = f.residencia_id
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             {$where}
             GROUP BY DATE(e.data_entrega)
             ORDER BY data DESC
             LIMIT 10"
        );
        $this->bind($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function documentStats(array $filters): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT d.tipo_documento,
                    COUNT(*) AS arquivos,
                    COALESCE(SUM(d.tamanho_bytes), 0) AS tamanho_total
             FROM documentos_anexos d
             LEFT JOIN familias f ON f.id = d.familia_id
             LEFT JOIN residencias rf ON rf.id = f.residencia_id
             LEFT JOIN residencias rd ON rd.id = d.residencia_id
             INNER JOIN residencias r ON r.id = COALESCE(rf.id, rd.id)
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             ' . $this->documentWhere($filters) . '
             GROUP BY d.tipo_documento
             ORDER BY arquivos DESC, d.tipo_documento
             LIMIT 8'
        );
        $this->bind($stmt, $this->documentParams($filters));
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function signatureStats(array $filters): array
    {
        $params = [];
        $conditions = ['1 = 1'];

        if (!empty($filters['data_inicio'])) {
            $conditions[] = 'c.solicitado_em >= :data_inicio';
            $params['data_inicio'] = $filters['data_inicio'] . ' 00:00:00';
        }

        if (!empty($filters['data_fim'])) {
            $conditions[] = 'c.solicitado_em <= :data_fim';
            $params['data_fim'] = $filters['data_fim'] . ' 23:59:59';
        }

        $stmt = Database::connection()->prepare(
            'SELECT c.documento_tipo,
                    COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN c.status = "pendente" THEN 1 ELSE 0 END), 0) AS pendentes,
                    COALESCE(SUM(CASE WHEN c.status = "autorizado" THEN 1 ELSE 0 END), 0) AS autorizadas,
                    COALESCE(SUM(CASE WHEN c.status = "negado" THEN 1 ELSE 0 END), 0) AS negadas
             FROM coassinaturas_documentos c
             WHERE ' . implode(' AND ', $conditions) . '
             GROUP BY c.documento_tipo
             ORDER BY total DESC, c.documento_tipo'
        );
        $this->bind($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function recomecarStats(array $filters): array
    {
        $eligibleSql = "(r.condicao_residencia <> 'nao_atingida' OR r.condicao_residencia IS NULL)
            AND (f.renda_familiar IS NULL OR f.renda_familiar <> 'acima_3_salarios')";

        return $this->single(
            "SELECT COUNT(DISTINCT f.id) AS total_familias,
                    COUNT(DISTINCT CASE WHEN {$eligibleSql} THEN f.id END) AS aptas,
                    COUNT(DISTINCT CASE WHEN NOT ({$eligibleSql}) THEN f.id END) AS inaptas
             FROM familias f
             INNER JOIN residencias r ON r.id = f.residencia_id
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             " . $this->cadastroWhere($filters, 'f'),
            $this->cadastroParams($filters)
        );
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
                    WHERE e.familia_id = f.id
                      AND COALESCE(e.status_operacional, 'entregue') = 'entregue'
                      AND e.deleted_at IS NULL
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
                    r.imovel, r.condicao_residencia,
                    a.localidade, a.tipo_evento, r.data_cadastro,
                    f.responsavel_nome, f.responsavel_cpf, f.telefone, f.quantidade_integrantes,
                    CASE
                        WHEN EXISTS (
                            SELECT 1
                            FROM entregas_ajuda e
                            WHERE e.familia_id = f.id
                              AND COALESCE(e.status_operacional, 'entregue') = 'entregue'
                              AND e.deleted_at IS NULL
                        ) THEN 'Entregue'
                        WHEN EXISTS (
                            SELECT 1
                            FROM entregas_ajuda e
                            WHERE e.familia_id = f.id
                              AND COALESCE(e.status_operacional, 'entregue') = 'registrado'
                              AND e.deleted_at IS NULL
                        ) THEN 'Registrado'
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

        if (!empty($filters['status_acao'])) {
            $conditions[] = 'a.status = :status_acao';
        }

        if (!empty($filters['q'])) {
            $familySearch = $familiaAlias !== ''
                ? ", ' ', COALESCE({$familiaAlias}.responsavel_nome, ''), ' ', COALESCE({$familiaAlias}.responsavel_cpf, ''), ' ', COALESCE({$familiaAlias}.telefone, '')"
                : '';
            $conditions[] = "(CONCAT(
                COALESCE(r.protocolo, ''), ' ',
                COALESCE(r.bairro_comunidade, ''), ' ',
                COALESCE(r.endereco, ''), ' ',
                COALESCE(a.localidade, ''), ' ',
                COALESCE(a.tipo_evento, '')
                {$familySearch}
            ) LIKE :q)";
        }

        if (!empty($filters['bairro'])) {
            $conditions[] = 'r.bairro_comunidade LIKE :bairro';
        }

        if (!empty($filters['imovel'])) {
            $conditions[] = 'r.imovel = :imovel';
        }

        if (!empty($filters['condicao'])) {
            $conditions[] = 'r.condicao_residencia = :condicao';
        }

        if ($familiaAlias !== '' && !empty($filters['cadastro'])) {
            $conditions[] = $filters['cadastro'] === 'concluido'
                ? "{$familiaAlias}.cadastro_concluido = 1"
                : "{$familiaAlias}.cadastro_concluido = 0";
        }

        if ($familiaAlias !== '' && !empty($filters['entregas'])) {
            $exists = "EXISTS (
                SELECT 1
                FROM entregas_ajuda e_status
                WHERE e_status.familia_id = {$familiaAlias}.id
                  AND COALESCE(e_status.status_operacional, 'entregue') = 'entregue'
                  AND e_status.deleted_at IS NULL
            )";
            $conditions[] = $filters['entregas'] === 'com_entrega' ? $exists : 'NOT ' . $exists;
        }

        if ($familiaAlias !== '' && !empty($filters['tipo_ajuda_id'])) {
            $conditions[] = "EXISTS (
                SELECT 1
                FROM entregas_ajuda e_tipo
                WHERE e_tipo.familia_id = {$familiaAlias}.id
                  AND e_tipo.tipo_ajuda_id = :tipo_ajuda_id
                  AND COALESCE(e_tipo.status_operacional, 'entregue') = 'entregue'
                  AND e_tipo.deleted_at IS NULL
            )";
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
            'COALESCE(e.status_operacional, "entregue") = "entregue"',
            'f.deleted_at IS NULL',
            'r.deleted_at IS NULL',
            'a.deleted_at IS NULL',
        ];

        if (!empty($filters['acao_id'])) {
            $conditions[] = 'a.id = :acao_id';
        }

        if (!empty($filters['status_acao'])) {
            $conditions[] = 'a.status = :status_acao';
        }

        if (!empty($filters['q'])) {
            $conditions[] = "(CONCAT(
                COALESCE(r.protocolo, ''), ' ',
                COALESCE(r.bairro_comunidade, ''), ' ',
                COALESCE(r.endereco, ''), ' ',
                COALESCE(a.localidade, ''), ' ',
                COALESCE(a.tipo_evento, ''), ' ',
                COALESCE(f.responsavel_nome, ''), ' ',
                COALESCE(f.responsavel_cpf, ''), ' ',
                COALESCE(f.telefone, '')
            ) LIKE :q)";
        }

        if (!empty($filters['bairro'])) {
            $conditions[] = 'r.bairro_comunidade LIKE :bairro';
        }

        if (!empty($filters['imovel'])) {
            $conditions[] = 'r.imovel = :imovel';
        }

        if (!empty($filters['condicao'])) {
            $conditions[] = 'r.condicao_residencia = :condicao';
        }

        if (!empty($filters['cadastro'])) {
            $conditions[] = $filters['cadastro'] === 'concluido'
                ? 'f.cadastro_concluido = 1'
                : 'f.cadastro_concluido = 0';
        }

        if (($filters['entregas'] ?? '') === 'sem_entrega') {
            $conditions[] = '1 = 0';
        }

        if (!empty($filters['tipo_ajuda_id'])) {
            $conditions[] = 'e.tipo_ajuda_id = :tipo_ajuda_id';
        }

        if (!empty($filters['data_inicio'])) {
            $conditions[] = 'e.data_entrega >= :data_inicio';
        }

        if (!empty($filters['data_fim'])) {
            $conditions[] = 'e.data_entrega <= :data_fim';
        }

        return 'WHERE ' . implode(' AND ', $conditions);
    }

    private function documentWhere(array $filters): string
    {
        $conditions = [
            'd.deleted_at IS NULL',
            'r.deleted_at IS NULL',
            'a.deleted_at IS NULL',
        ];

        if (!empty($filters['acao_id'])) {
            $conditions[] = 'a.id = :acao_id';
        }

        if (!empty($filters['status_acao'])) {
            $conditions[] = 'a.status = :status_acao';
        }

        if (!empty($filters['q'])) {
            $conditions[] = "(CONCAT(
                COALESCE(r.protocolo, ''), ' ',
                COALESCE(r.bairro_comunidade, ''), ' ',
                COALESCE(r.endereco, ''), ' ',
                COALESCE(a.localidade, ''), ' ',
                COALESCE(a.tipo_evento, ''), ' ',
                COALESCE(d.nome_original, '')
            ) LIKE :q)";
        }

        if (!empty($filters['bairro'])) {
            $conditions[] = 'r.bairro_comunidade LIKE :bairro';
        }

        if (!empty($filters['imovel'])) {
            $conditions[] = 'r.imovel = :imovel';
        }

        if (!empty($filters['condicao'])) {
            $conditions[] = 'r.condicao_residencia = :condicao';
        }

        if (!empty($filters['data_inicio'])) {
            $conditions[] = 'd.criado_em >= :data_inicio';
        }

        if (!empty($filters['data_fim'])) {
            $conditions[] = 'd.criado_em <= :data_fim';
        }

        return 'WHERE ' . implode(' AND ', $conditions);
    }

    private function cadastroParams(array $filters): array
    {
        $params = [];

        if (!empty($filters['acao_id'])) {
            $params['acao_id'] = (int) $filters['acao_id'];
        }

        if (!empty($filters['tipo_ajuda_id'])) {
            $params['tipo_ajuda_id'] = (int) $filters['tipo_ajuda_id'];
        }

        if (!empty($filters['status_acao'])) {
            $params['status_acao'] = $filters['status_acao'];
        }

        if (!empty($filters['q'])) {
            $params['q'] = '%' . $filters['q'] . '%';
        }

        if (!empty($filters['bairro'])) {
            $params['bairro'] = '%' . $filters['bairro'] . '%';
        }

        if (!empty($filters['imovel'])) {
            $params['imovel'] = $filters['imovel'];
        }

        if (!empty($filters['condicao'])) {
            $params['condicao'] = $filters['condicao'];
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

    private function documentParams(array $filters): array
    {
        $params = $this->cadastroParams($filters);
        unset($params['tipo_ajuda_id']);

        return $params;
    }

    private function bind(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue(':' . $key, $value, $type);
        }
    }
}
