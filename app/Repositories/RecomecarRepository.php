<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class RecomecarRepository
{
    private const ANALYSIS_ACTIONS = ['editou_recomecar_dados', 'analisou_recomecar_familia'];

    public function __construct()
    {
        $this->ensureAnalysisAssignmentSchema();
    }

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
                        INNER JOIN tipos_ajuda t ON t.id = e.tipo_ajuda_id
                        WHERE e.familia_id = f.id
                          AND e.deleted_at IS NULL
                          AND " . $this->recomecarTypeSql('t') . "
                    ) AS total_entregas,
                    (
                        SELECT MAX(e.data_entrega)
                        FROM entregas_ajuda e
                        INNER JOIN tipos_ajuda t ON t.id = e.tipo_ajuda_id
                        WHERE e.familia_id = f.id
                          AND e.deleted_at IS NULL
                          AND " . $this->recomecarTypeSql('t') . "
                    ) AS ultima_entrega,
                    CASE
                        WHEN " . $this->recomecarDeliveredExistsSql() . " THEN 'entregue'
                        WHEN " . $this->recomecarRegisteredExistsSql() . " THEN 'registrado'
                        ELSE 'nao_entregue'
                    END AS status_entrega,
                    CASE WHEN " . $this->eligibleSql() . " THEN 'apta' ELSE 'inapta' END AS aptidao,
                    " . $this->ineligibilityReasonSql() . " AS motivo_inaptidao
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

    public function countAnalysisRecords(array $filters): int
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

    public function analysisSummary(array $filters): array
    {
        [$where, $params] = $this->buildWhere($filters, false);
        $analysisExists = $this->analysisExistsSql();
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) AS total_familias,
                    COALESCE(SUM(CASE WHEN " . $this->eligibleSql() . " THEN 1 ELSE 0 END), 0) AS familias_aptas,
                    COALESCE(SUM(CASE WHEN " . $this->eligibleSql() . " THEN 0 ELSE 1 END), 0) AS familias_inaptas,
                    COALESCE(SUM(CASE WHEN {$analysisExists} THEN 1 ELSE 0 END), 0) AS familias_analisadas,
                    COALESCE(SUM(CASE WHEN {$analysisExists} THEN 0 ELSE 1 END), 0) AS familias_pendentes
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
            'familias_analisadas' => 0,
            'familias_pendentes' => 0,
        ];
    }

    public function analysisRecords(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $stmt = Database::connection()->prepare(
            "SELECT " . $this->analysisSelectSql() . "
             FROM familias f
             INNER JOIN residencias r ON r.id = f.residencia_id
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             INNER JOIN municipios m ON m.id = r.municipio_id
             {$where}
             ORDER BY aptidao ASC, r.data_cadastro ASC, r.id ASC, f.id ASC
             LIMIT :limit OFFSET :offset"
        );
        $this->bind($stmt, $params);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findAnalysisRecord(int $familiaId): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT " . $this->analysisSelectSql() . "
             FROM familias f
             INNER JOIN residencias r ON r.id = f.residencia_id
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             INNER JOIN municipios m ON m.id = r.municipio_id
             WHERE f.id = :familia_id
               AND f.deleted_at IS NULL
               AND r.deleted_at IS NULL
               AND a.deleted_at IS NULL
               AND " . $this->recomecarDeliveryExistsSql() . "
             LIMIT 1"
        );
        $stmt->bindValue(':familia_id', $familiaId, PDO::PARAM_INT);
        $stmt->execute();

        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($record) ? $record : null;
    }

    public function updateAnalysisRecord(int $familiaId, array $data): void
    {
        $record = $this->findAnalysisRecord($familiaId);

        if ($record === null) {
            return;
        }

        $connection = Database::connection();
        $connection->beginTransaction();

        try {
            $residenciaStmt = $connection->prepare(
                'UPDATE residencias
                 SET bairro_comunidade = :bairro_comunidade,
                     endereco = :endereco,
                     complemento = :complemento,
                     imovel = :imovel,
                     condicao_residencia = :condicao_residencia,
                     latitude = :latitude,
                     longitude = :longitude,
                     quantidade_familias = :quantidade_familias
                 WHERE id = :id
                   AND deleted_at IS NULL'
            );
            $residenciaStmt->bindValue(':id', (int) $record['residencia_id'], PDO::PARAM_INT);
            $residenciaStmt->bindValue(':bairro_comunidade', $data['bairro_comunidade']);
            $residenciaStmt->bindValue(':endereco', $data['endereco']);
            $residenciaStmt->bindValue(':complemento', $data['complemento'] !== '' ? $data['complemento'] : null);
            $residenciaStmt->bindValue(':imovel', $data['imovel'] !== '' ? $data['imovel'] : null);
            $residenciaStmt->bindValue(':condicao_residencia', $data['condicao_residencia'] !== '' ? $data['condicao_residencia'] : null);
            $residenciaStmt->bindValue(':latitude', $data['latitude'] !== '' ? $data['latitude'] : null);
            $residenciaStmt->bindValue(':longitude', $data['longitude'] !== '' ? $data['longitude'] : null);
            $residenciaStmt->bindValue(':quantidade_familias', (int) $data['quantidade_familias'], PDO::PARAM_INT);
            $residenciaStmt->execute();

            $familiaStmt = $connection->prepare(
                'UPDATE familias
                 SET responsavel_nome = :responsavel_nome,
                     responsavel_cpf = :responsavel_cpf,
                     responsavel_rg = :responsavel_rg,
                     responsavel_sexo = :responsavel_sexo,
                     responsavel_orgao_expedidor = :responsavel_orgao_expedidor,
                     data_nascimento = :data_nascimento,
                     telefone = :telefone,
                     email = :email,
                     quantidade_integrantes = :quantidade_integrantes,
                     possui_criancas = :possui_criancas,
                     possui_idosos = :possui_idosos,
                     possui_pcd = :possui_pcd,
                     possui_gestantes = :possui_gestantes,
                     renda_familiar = :renda_familiar,
                     perdas_bens_moveis = :perdas_bens_moveis,
                     situacao_familia = :situacao_familia,
                     recebe_beneficio_social = :recebe_beneficio_social,
                     beneficio_social_nome = :beneficio_social_nome,
                     cadastro_concluido = :cadastro_concluido,
                     conclusao_observacoes = :conclusao_observacoes,
                     representante_nome = :representante_nome,
                     representante_cpf = :representante_cpf,
                     representante_rg = :representante_rg,
                     representante_orgao_expedidor = :representante_orgao_expedidor,
                     representante_data_nascimento = :representante_data_nascimento,
                     representante_sexo = :representante_sexo,
                     representante_telefone = :representante_telefone,
                     representante_email = :representante_email
                 WHERE id = :id
                   AND deleted_at IS NULL'
            );
            $familiaStmt->bindValue(':id', $familiaId, PDO::PARAM_INT);
            $familiaStmt->bindValue(':responsavel_nome', $data['responsavel_nome']);
            $familiaStmt->bindValue(':responsavel_cpf', $data['responsavel_cpf']);
            $familiaStmt->bindValue(':responsavel_rg', $data['responsavel_rg'] !== '' ? $data['responsavel_rg'] : null);
            $familiaStmt->bindValue(':responsavel_sexo', $data['responsavel_sexo'] !== '' ? $data['responsavel_sexo'] : null);
            $familiaStmt->bindValue(':responsavel_orgao_expedidor', $data['responsavel_orgao_expedidor'] !== '' ? $data['responsavel_orgao_expedidor'] : null);
            $familiaStmt->bindValue(':data_nascimento', $data['data_nascimento'] !== '' ? $data['data_nascimento'] : null);
            $familiaStmt->bindValue(':telefone', $data['telefone'] !== '' ? $data['telefone'] : null);
            $familiaStmt->bindValue(':email', $data['email'] !== '' ? $data['email'] : null);
            $familiaStmt->bindValue(':quantidade_integrantes', (int) $data['quantidade_integrantes'], PDO::PARAM_INT);
            $familiaStmt->bindValue(':possui_criancas', !empty($data['possui_criancas']) ? 1 : 0, PDO::PARAM_INT);
            $familiaStmt->bindValue(':possui_idosos', !empty($data['possui_idosos']) ? 1 : 0, PDO::PARAM_INT);
            $familiaStmt->bindValue(':possui_pcd', !empty($data['possui_pcd']) ? 1 : 0, PDO::PARAM_INT);
            $familiaStmt->bindValue(':possui_gestantes', !empty($data['possui_gestantes']) ? 1 : 0, PDO::PARAM_INT);
            $familiaStmt->bindValue(':renda_familiar', $data['renda_familiar'] !== '' ? $data['renda_familiar'] : null);
            $familiaStmt->bindValue(':perdas_bens_moveis', $data['perdas_bens_moveis'] !== '' ? $data['perdas_bens_moveis'] : null);
            $familiaStmt->bindValue(':situacao_familia', $data['situacao_familia'] !== '' ? $data['situacao_familia'] : null);
            $familiaStmt->bindValue(':recebe_beneficio_social', !empty($data['recebe_beneficio_social']) ? 1 : 0, PDO::PARAM_INT);
            $familiaStmt->bindValue(':beneficio_social_nome', $data['beneficio_social_nome'] !== '' ? $data['beneficio_social_nome'] : null);
            $familiaStmt->bindValue(':cadastro_concluido', !empty($data['cadastro_concluido']) ? 1 : 0, PDO::PARAM_INT);
            $familiaStmt->bindValue(':conclusao_observacoes', $data['conclusao_observacoes'] !== '' ? $data['conclusao_observacoes'] : null);
            $familiaStmt->bindValue(':representante_nome', $data['representante_nome'] !== '' ? $data['representante_nome'] : null);
            $familiaStmt->bindValue(':representante_cpf', $data['representante_cpf'] !== '' ? $data['representante_cpf'] : null);
            $familiaStmt->bindValue(':representante_rg', $data['representante_rg'] !== '' ? $data['representante_rg'] : null);
            $familiaStmt->bindValue(':representante_orgao_expedidor', $data['representante_orgao_expedidor'] !== '' ? $data['representante_orgao_expedidor'] : null);
            $familiaStmt->bindValue(':representante_data_nascimento', $data['representante_data_nascimento'] !== '' ? $data['representante_data_nascimento'] : null);
            $familiaStmt->bindValue(':representante_sexo', $data['representante_sexo'] !== '' ? $data['representante_sexo'] : null);
            $familiaStmt->bindValue(':representante_telefone', $data['representante_telefone'] !== '' ? $data['representante_telefone'] : null);
            $familiaStmt->bindValue(':representante_email', $data['representante_email'] !== '' ? $data['representante_email'] : null);
            $familiaStmt->execute();

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    public function documentsForRecords(array $records): array
    {
        $familiaIds = array_values(array_unique(array_filter(array_map(
            static fn (array $record): int => (int) ($record['familia_id'] ?? 0),
            $records
        ), static fn (int $id): bool => $id > 0)));
        $residenciaIds = array_values(array_unique(array_filter(array_map(
            static fn (array $record): int => (int) ($record['residencia_id'] ?? 0),
            $records
        ), static fn (int $id): bool => $id > 0)));

        if ($familiaIds === [] && $residenciaIds === []) {
            return [];
        }

        $familiaPlaceholders = [];
        $residenciaPlaceholders = [];
        $params = [];

        foreach ($familiaIds as $index => $id) {
            $key = ':familia_id_' . $index;
            $familiaPlaceholders[] = $key;
            $params[$key] = $id;
        }

        foreach ($residenciaIds as $index => $id) {
            $key = ':residencia_id_' . $index;
            $residenciaPlaceholders[] = $key;
            $params[$key] = $id;
        }

        $conditions = [];
        if ($familiaPlaceholders !== []) {
            $conditions[] = 'd.familia_id IN (' . implode(', ', $familiaPlaceholders) . ')';
        }

        if ($residenciaPlaceholders !== []) {
            $conditions[] = '(d.residencia_id IN (' . implode(', ', $residenciaPlaceholders) . ') AND d.familia_id IS NULL)';
        }

        $stmt = Database::connection()->prepare(
            'SELECT d.id, d.tipo_documento, d.nome_original, d.mime_type, d.tamanho_bytes,
                    d.criado_em, d.residencia_id, d.familia_id
             FROM documentos_anexos d
             WHERE d.deleted_at IS NULL
               AND (' . implode(' OR ', $conditions) . ')
             ORDER BY d.criado_em DESC, d.id DESC'
        );

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        }

        $stmt->execute();

        $documents = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $document) {
            $familiaId = (int) ($document['familia_id'] ?? 0);
            if ($familiaId > 0) {
                $documents['familia:' . $familiaId][] = $document;
                continue;
            }

            $residenciaId = (int) ($document['residencia_id'] ?? 0);
            if ($residenciaId > 0) {
                $documents['residencia:' . $residenciaId][] = $document;
            }
        }

        return $documents;
    }

    public function historyForRecords(array $familiaIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): int => (int) $id,
            $familiaIds
        ), static fn (int $id): bool => $id > 0)));

        if ($ids === []) {
            return [];
        }

        $idPlaceholders = [];
        foreach ($ids as $index => $id) {
            $idPlaceholders[] = ':familia_id_' . $index;
        }

        $actionPlaceholders = [];
        foreach (self::ANALYSIS_ACTIONS as $index => $action) {
            $actionPlaceholders[] = ':action_' . $index;
        }

        $stmt = Database::connection()->prepare(
            'SELECT l.id, l.usuario_id, l.acao, l.entidade_id, l.descricao, l.criado_em,
                    u.nome AS usuario_nome
             FROM logs_sistema l
             LEFT JOIN usuarios u ON u.id = l.usuario_id
             WHERE l.entidade = "recomecar_analise"
               AND l.entidade_id IN (' . implode(', ', $idPlaceholders) . ')
               AND l.acao IN (' . implode(', ', $actionPlaceholders) . ')
             ORDER BY l.criado_em DESC, l.id DESC'
        );

        foreach ($ids as $index => $id) {
            $stmt->bindValue(':familia_id_' . $index, $id, PDO::PARAM_INT);
        }

        foreach (self::ANALYSIS_ACTIONS as $index => $action) {
            $stmt->bindValue(':action_' . $index, $action);
        }

        $stmt->execute();

        $history = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $log) {
            $history[(int) ($log['entidade_id'] ?? 0)][] = $log;
        }

        return $history;
    }

    public function analysisAssignmentUsers(): array
    {
        return Database::connection()
            ->query(
                "SELECT id, nome, cpf, email, perfil, orgao, unidade_setor
                 FROM usuarios
                 WHERE ativo = 1
                   AND deleted_at IS NULL
                   AND perfil IN ('gestor', 'administrador')
                 ORDER BY nome ASC"
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function analysisAssignmentSummary(array $filters): array
    {
        [$where, $params] = $this->buildWhere($filters, false);
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) AS total_recorte,
                    COALESCE(SUM(CASE WHEN raa.usuario_id IS NULL THEN 0 ELSE 1 END), 0) AS atribuidas,
                    COALESCE(SUM(CASE WHEN raa.usuario_id IS NULL THEN 1 ELSE 0 END), 0) AS sem_atribuicao,
                    COUNT(DISTINCT raa.usuario_id) AS analistas_com_registros
             FROM familias f
             INNER JOIN residencias r ON r.id = f.residencia_id
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             INNER JOIN municipios m ON m.id = r.municipio_id
             LEFT JOIN recomecar_analise_atribuicoes raa ON raa.familia_id = f.id
             {$where}"
        );
        $this->bind($stmt, $params);
        $stmt->execute();

        $summary = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($summary) ? $summary : [
            'total_recorte' => 0,
            'atribuidas' => 0,
            'sem_atribuicao' => 0,
            'analistas_com_registros' => 0,
        ];
    }

    public function userAnalysisQueueStatus(int $userId): array
    {
        if ($userId <= 0) {
            return $this->emptyQueueStatus();
        }

        $analysisExists = $this->analysisExistsSql();
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN {$analysisExists} THEN 1 ELSE 0 END), 0) AS analisadas,
                    COALESCE(SUM(CASE WHEN {$analysisExists} THEN 0 ELSE 1 END), 0) AS pendentes,
                    COUNT(*) AS abertas,
                    NULL AS concluido_em,
                    MAX(u_autor.nome) AS distribuidor_nome
             FROM recomecar_analise_atribuicoes raa
             INNER JOIN familias f ON f.id = raa.familia_id
             INNER JOIN residencias r ON r.id = f.residencia_id
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             LEFT JOIN usuarios u_autor ON u_autor.id = raa.atribuido_por
             WHERE raa.usuario_id = :usuario_id
               AND raa.concluido_em IS NULL
               AND f.deleted_at IS NULL
               AND r.deleted_at IS NULL
               AND a.deleted_at IS NULL"
        );
        $stmt->bindValue(':usuario_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $status = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($status) ? $status : $this->emptyQueueStatus();
    }

    public function managedAnalysisQueueStatus(int $managerId): array
    {
        if ($managerId <= 0) {
            return [];
        }

        $analysisExists = $this->analysisExistsSql();
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(raa.filtros_hash, CONCAT('sem_hash_', raa.estrategia, '_', a.id)) AS distribuicao_chave,
                    raa.estrategia,
                    a.id AS acao_id,
                    a.localidade,
                    a.tipo_evento,
                    m.nome AS municipio_nome,
                    m.uf,
                    raa.usuario_id,
                    u.nome AS usuario_nome,
                    u.perfil AS usuario_perfil,
                    COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN {$analysisExists} THEN 1 ELSE 0 END), 0) AS analisadas,
                    COALESCE(SUM(CASE WHEN {$analysisExists} THEN 0 ELSE 1 END), 0) AS pendentes,
                    COALESCE(SUM(CASE WHEN raa.concluido_em IS NULL THEN 1 ELSE 0 END), 0) AS abertas,
                    MIN(raa.criado_em) AS distribuido_em,
                    MAX(raa.concluido_em) AS concluido_em,
                    MAX(raa.atualizado_em) AS atualizado_em
             FROM recomecar_analise_atribuicoes raa
             INNER JOIN usuarios u ON u.id = raa.usuario_id
             INNER JOIN familias f ON f.id = raa.familia_id
             INNER JOIN residencias r ON r.id = f.residencia_id
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             INNER JOIN municipios m ON m.id = r.municipio_id
             WHERE raa.atribuido_por = :manager_id
               AND f.deleted_at IS NULL
               AND r.deleted_at IS NULL
               AND a.deleted_at IS NULL
               AND EXISTS (
                    SELECT 1
                    FROM recomecar_analise_atribuicoes raa_aberta
                    INNER JOIN familias f_aberta ON f_aberta.id = raa_aberta.familia_id
                    INNER JOIN residencias r_aberta ON r_aberta.id = f_aberta.residencia_id
                    WHERE raa_aberta.atribuido_por = raa.atribuido_por
                      AND raa_aberta.estrategia = raa.estrategia
                      AND COALESCE(raa_aberta.filtros_hash, '') = COALESCE(raa.filtros_hash, '')
                      AND r_aberta.acao_id = a.id
                      AND raa_aberta.concluido_em IS NULL
                      AND f_aberta.deleted_at IS NULL
                      AND r_aberta.deleted_at IS NULL
               )
             GROUP BY distribuicao_chave, raa.estrategia, a.id, a.localidade, a.tipo_evento, m.nome, m.uf, raa.usuario_id, u.nome, u.perfil
             ORDER BY distribuido_em DESC, a.id DESC, u.nome ASC"
        );
        $stmt->bindValue(':manager_id', $managerId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function managedAnalysisDistributionHistory(int $managerId): array
    {
        if ($managerId <= 0) {
            return [];
        }

        $analysisExists = $this->analysisExistsSql();
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(raa.filtros_hash, CONCAT('sem_hash_', raa.estrategia, '_', a.id)) AS distribuicao_chave,
                    raa.estrategia,
                    raa.filtros_json,
                    a.id AS acao_id,
                    a.localidade,
                    a.tipo_evento,
                    m.nome AS municipio_nome,
                    m.uf,
                    raa.usuario_id,
                    u.nome AS usuario_nome,
                    u.perfil AS usuario_perfil,
                    COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN {$analysisExists} THEN 1 ELSE 0 END), 0) AS analisadas,
                    COALESCE(SUM(CASE WHEN {$analysisExists} THEN 0 ELSE 1 END), 0) AS pendentes,
                    COALESCE(SUM(CASE WHEN raa.concluido_em IS NULL THEN 1 ELSE 0 END), 0) AS abertas,
                    MIN(raa.criado_em) AS distribuido_em,
                    MAX(raa.concluido_em) AS concluido_em,
                    MAX(raa.atualizado_em) AS atualizado_em
             FROM recomecar_analise_atribuicoes raa
             INNER JOIN usuarios u ON u.id = raa.usuario_id
             INNER JOIN familias f ON f.id = raa.familia_id
             INNER JOIN residencias r ON r.id = f.residencia_id
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             INNER JOIN municipios m ON m.id = r.municipio_id
             WHERE raa.atribuido_por = :manager_id
               AND f.deleted_at IS NULL
               AND r.deleted_at IS NULL
               AND a.deleted_at IS NULL
             GROUP BY distribuicao_chave, raa.estrategia, raa.filtros_json, a.id, a.localidade, a.tipo_evento, m.nome, m.uf, raa.usuario_id, u.nome, u.perfil
             ORDER BY distribuido_em DESC, a.id DESC, u.nome ASC"
        );
        $stmt->bindValue(':manager_id', $managerId, PDO::PARAM_INT);
        $stmt->execute();

        $history = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = (string) ($row['distribuicao_chave'] ?? '') . ':' . (string) ($row['estrategia'] ?? '') . ':' . (string) ($row['acao_id'] ?? '');
            $filters = json_decode((string) ($row['filtros_json'] ?? ''), true);
            if (!is_array($filters)) {
                $filters = [];
            }

            if (!isset($history[$key])) {
                $history[$key] = [
                    'chave' => $key,
                    'acao_id' => (int) ($row['acao_id'] ?? 0),
                    'acao_label' => trim((string) ($row['municipio_nome'] ?? '') . '/' . (string) ($row['uf'] ?? '') . ' - ' . (string) ($row['localidade'] ?? '') . ' - ' . (string) ($row['tipo_evento'] ?? '')),
                    'estrategia' => (string) ($row['estrategia'] ?? ''),
                    'periodo_inicio' => (string) ($filters['data_inicio'] ?? ''),
                    'periodo_fim' => (string) ($filters['data_fim'] ?? ''),
                    'distribuido_em' => (string) ($row['distribuido_em'] ?? ''),
                    'atualizado_em' => (string) ($row['atualizado_em'] ?? ''),
                    'total' => 0,
                    'analisadas' => 0,
                    'pendentes' => 0,
                    'abertas' => 0,
                    'analistas' => [],
                ];
            }

            $total = (int) ($row['total'] ?? 0);
            $analisadas = (int) ($row['analisadas'] ?? 0);
            $pendentes = (int) ($row['pendentes'] ?? 0);
            $abertas = (int) ($row['abertas'] ?? 0);
            $history[$key]['total'] += $total;
            $history[$key]['analisadas'] += $analisadas;
            $history[$key]['pendentes'] += $pendentes;
            $history[$key]['abertas'] += $abertas;
            $history[$key]['analistas'][] = [
                'usuario_id' => (int) ($row['usuario_id'] ?? 0),
                'nome' => (string) ($row['usuario_nome'] ?? ''),
                'perfil' => (string) ($row['usuario_perfil'] ?? ''),
                'total' => $total,
                'analisadas' => $analisadas,
                'pendentes' => $pendentes,
                'abertas' => $abertas,
                'concluido_em' => (string) ($row['concluido_em'] ?? ''),
            ];
        }

        foreach ($history as &$distribution) {
            $distribution['status'] = (int) ($distribution['abertas'] ?? 0) > 0 ? 'em_andamento' : 'concluida';
        }
        unset($distribution);

        return array_values($history);
    }

    public function assignAnalysisRecords(array $filters, array $userIds, string $strategy, int $assignedBy): array
    {
        $records = $this->assignmentRecords($filters, $strategy);
        $users = $this->assignmentUsersByIds($userIds);

        if ($records === [] || $users === []) {
            return [
                'total' => count($records),
                'assigned' => 0,
                'users' => count($users),
                'by_user' => [],
            ];
        }

        $userIds = array_map(static fn (array $user): int => (int) $user['id'], $users);
        $loads = $this->assignmentLoads($userIds);
        foreach ($userIds as $userId) {
            $loads[$userId] = (int) ($loads[$userId] ?? 0);
        }

        $connection = Database::connection();
        $stmt = $connection->prepare(
            'INSERT INTO recomecar_analise_atribuicoes
                (familia_id, usuario_id, atribuido_por, estrategia, filtros_hash, filtros_json, concluido_em, concluido_por, criado_em)
             VALUES
                (:familia_id, :usuario_id, :atribuido_por, :estrategia, :filtros_hash, :filtros_json, NULL, NULL, NOW())
             ON DUPLICATE KEY UPDATE
                usuario_id = VALUES(usuario_id),
                atribuido_por = VALUES(atribuido_por),
                estrategia = VALUES(estrategia),
                filtros_hash = VALUES(filtros_hash),
                filtros_json = VALUES(filtros_json),
                concluido_em = NULL,
                concluido_por = NULL,
                atualizado_em = NOW()'
        );
        $filtersJson = json_encode($filters, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $filtersHash = hash('sha256', (string) $filtersJson);
        $assigned = 0;
        $byUser = [];

        $connection->beginTransaction();
        try {
            $totalRecords = count($records);
            foreach ($records as $index => $record) {
                $targetUserId = $this->targetAssignmentUserId($strategy, $index, $totalRecords, $loads, $userIds);
                $stmt->bindValue(':familia_id', (int) $record['familia_id'], PDO::PARAM_INT);
                $stmt->bindValue(':usuario_id', $targetUserId, PDO::PARAM_INT);
                $stmt->bindValue(':atribuido_por', $assignedBy > 0 ? $assignedBy : null, $assignedBy > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
                $stmt->bindValue(':estrategia', $strategy);
                $stmt->bindValue(':filtros_hash', $filtersHash);
                $stmt->bindValue(':filtros_json', $filtersJson);
                $stmt->execute();

                $loads[$targetUserId]++;
                $byUser[$targetUserId] = (int) ($byUser[$targetUserId] ?? 0) + 1;
                $assigned++;
            }

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return [
            'total' => count($records),
            'assigned' => $assigned,
            'users' => count($users),
            'by_user' => $byUser,
        ];
    }

    public function completeUserAnalysisQueue(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE recomecar_analise_atribuicoes
             SET concluido_em = NOW(),
                 concluido_por = :concluido_por,
                 atualizado_em = NOW()
             WHERE usuario_id = :usuario_id
               AND concluido_em IS NULL'
        );
        $stmt->bindValue(':concluido_por', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':usuario_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function previewAnalysisDistribution(array $filters, array $userIds, string $strategy): array
    {
        $records = $this->assignmentRecords($filters, $strategy);
        $users = $this->assignmentUsersByIds($userIds);

        if ($records === [] || $users === []) {
            return [
                'total' => count($records),
                'users' => count($users),
                'by_user' => [],
            ];
        }

        $orderedUserIds = array_map(static fn (array $user): int => (int) $user['id'], $users);
        $loads = array_fill_keys($orderedUserIds, 0);
        $totalRecords = count($records);
        $byUser = [];

        foreach ($users as $user) {
            $userId = (int) ($user['id'] ?? 0);
            $byUser[$userId] = [
                'id' => $userId,
                'nome' => (string) ($user['nome'] ?? ''),
                'perfil' => (string) ($user['perfil'] ?? ''),
                'total' => 0,
            ];
        }

        foreach ($records as $index => $record) {
            $targetUserId = $this->targetAssignmentUserId($strategy, $index, $totalRecords, $loads, $orderedUserIds);
            if (!isset($byUser[$targetUserId])) {
                continue;
            }

            $loads[$targetUserId] = (int) ($loads[$targetUserId] ?? 0) + 1;
            $byUser[$targetUserId]['total']++;
        }

        return [
            'total' => $totalRecords,
            'users' => count($users),
            'by_user' => array_values($byUser),
        ];
    }

    public function canUserAnalyzeFamily(int $familiaId, int $userId, bool $isAdministrator): bool
    {
        if ($isAdministrator) {
            return true;
        }

        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM recomecar_analise_atribuicoes
             WHERE familia_id = :familia_id
               AND usuario_id = :usuario_id
               AND concluido_em IS NULL'
        );
        $stmt->bindValue(':familia_id', $familiaId, PDO::PARAM_INT);
        $stmt->bindValue(':usuario_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn() > 0;
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
            $this->recomecarDeliveryExistsSql(),
        ];
        $params = [];

        if (!empty($filters['familia_id'])) {
            $conditions[] = 'f.id = :familia_id';
            $params['familia_id'] = (int) $filters['familia_id'];
        }

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

        if (($filters['status_entrega'] ?? '') === 'registrado') {
            $conditions[] = $this->recomecarRegisteredExistsSql();
        } elseif (($filters['status_entrega'] ?? '') === 'entregue') {
            $conditions[] = $this->recomecarDeliveredExistsSql();
        } elseif (($filters['status_entrega'] ?? '') === 'nao_entregue') {
            $conditions[] = 'NOT (' . $this->recomecarDeliveryExistsSql() . ')';
        }

        if ($applyEligibility) {
            if (($filters['aptidao'] ?? 'apta') === 'apta') {
                $conditions[] = $this->eligibleSql();
            } elseif (($filters['aptidao'] ?? '') === 'inapta') {
                $conditions[] = 'NOT (' . $this->eligibleSql() . ')';
            }
        }

        if (($filters['analise'] ?? '') === 'analisado') {
            $conditions[] = $this->analysisExistsSql();
        } elseif (($filters['analise'] ?? '') === 'pendente') {
            $conditions[] = 'NOT (' . $this->analysisExistsSql() . ')';
        }

        if (!empty($filters['analista_id'])) {
            $conditions[] = 'EXISTS (
                SELECT 1
                FROM recomecar_analise_atribuicoes raa_filter
                WHERE raa_filter.familia_id = f.id
                  AND raa_filter.usuario_id = :analista_id
            )';
            $params['analista_id'] = (int) $filters['analista_id'];
        }

        if (!empty($filters['analista_usuario_id'])) {
            $conditions[] = 'EXISTS (
                SELECT 1
                FROM recomecar_analise_atribuicoes raa_user
                WHERE raa_user.familia_id = f.id
                  AND raa_user.usuario_id = :analista_usuario_id
            )';
            $params['analista_usuario_id'] = (int) $filters['analista_usuario_id'];
        }

        return ['WHERE ' . implode(' AND ', $conditions), $params];
    }

    private function assignmentRecords(array $filters, string $strategy): array
    {
        $distributionFilters = $filters;
        unset($distributionFilters['analista_id'], $distributionFilters['analista_usuario_id']);

        [$where, $params] = $this->buildWhere($distributionFilters);
        $order = match ($strategy) {
            'pares_impares' => 'f.id ASC',
            'blocos' => 'r.data_cadastro ASC, r.id ASC, f.id ASC',
            default => 'r.data_cadastro ASC, a.data_evento ASC, r.id ASC, f.id ASC',
        };
        $stmt = Database::connection()->prepare(
            "SELECT f.id AS familia_id,
                    CASE WHEN " . $this->eligibleSql() . " THEN 'apta' ELSE 'inapta' END AS aptidao,
                    m.nome AS municipio_nome,
                    a.localidade,
                    r.bairro_comunidade,
                    r.data_cadastro
             FROM familias f
             INNER JOIN residencias r ON r.id = f.residencia_id
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             INNER JOIN municipios m ON m.id = r.municipio_id
             {$where}
             ORDER BY {$order}"
        );
        $this->bind($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function assignmentUsersByIds(array $ids): array
    {
        $normalizedIds = array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): int => (int) $id,
            $ids
        ), static fn (int $id): bool => $id > 0)));

        if ($normalizedIds === []) {
            return [];
        }

        $placeholders = [];
        foreach ($normalizedIds as $index => $id) {
            $placeholders[] = ':id_' . $index;
        }

        $stmt = Database::connection()->prepare(
            "SELECT id, nome, perfil
             FROM usuarios
             WHERE ativo = 1
               AND deleted_at IS NULL
               AND perfil IN ('gestor', 'administrador')
               AND id IN (" . implode(', ', $placeholders) . ")
             ORDER BY nome ASC"
        );

        foreach ($normalizedIds as $index => $id) {
            $stmt->bindValue(':id_' . $index, $id, PDO::PARAM_INT);
        }

        $stmt->execute();

        $usersById = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $user) {
            $usersById[(int) ($user['id'] ?? 0)] = $user;
        }

        $orderedUsers = [];
        foreach ($normalizedIds as $id) {
            if (isset($usersById[$id])) {
                $orderedUsers[] = $usersById[$id];
            }
        }

        return $orderedUsers;
    }

    private function assignmentLoads(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $placeholders = [];
        foreach ($userIds as $index => $id) {
            $placeholders[] = ':user_id_' . $index;
        }

        $stmt = Database::connection()->prepare(
            'SELECT usuario_id, COUNT(*) AS total
             FROM recomecar_analise_atribuicoes
             WHERE usuario_id IN (' . implode(', ', $placeholders) . ')
             GROUP BY usuario_id'
        );

        foreach ($userIds as $index => $id) {
            $stmt->bindValue(':user_id_' . $index, (int) $id, PDO::PARAM_INT);
        }

        $stmt->execute();

        $loads = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $loads[(int) ($row['usuario_id'] ?? 0)] = (int) ($row['total'] ?? 0);
        }

        return $loads;
    }

    private function leastLoadedUserId(array $loads, array $userIds): int
    {
        $selectedUserId = (int) $userIds[0];
        $selectedLoad = (int) ($loads[$selectedUserId] ?? 0);

        foreach ($userIds as $userId) {
            $load = (int) ($loads[$userId] ?? 0);
            if ($load < $selectedLoad) {
                $selectedUserId = (int) $userId;
                $selectedLoad = $load;
            }
        }

        return $selectedUserId;
    }

    private function targetAssignmentUserId(string $strategy, int $index, int $totalRecords, array $loads, array $userIds): int
    {
        $totalUsers = count($userIds);
        if ($totalUsers <= 0) {
            return 0;
        }

        if ($strategy === 'pares_impares') {
            return (int) $userIds[$index % $totalUsers];
        }

        if ($strategy === 'blocos') {
            return $this->balancedSequentialUserId($index, $totalRecords, $userIds);
        }

        if ($strategy === 'periodo') {
            return $this->balancedSequentialUserId($index, $totalRecords, $userIds);
        }

        return $this->leastLoadedUserId($loads, $userIds);
    }

    private function balancedSequentialUserId(int $index, int $totalRecords, array $userIds): int
    {
        $totalUsers = count($userIds);
        if ($totalUsers <= 1) {
            return (int) ($userIds[0] ?? 0);
        }

        $baseSize = intdiv(max(0, $totalRecords), $totalUsers);
        $remainder = max(0, $totalRecords) % $totalUsers;
        $cursor = 0;

        foreach ($userIds as $position => $userId) {
            $chunkSize = $baseSize + ($position < $remainder ? 1 : 0);
            if ($chunkSize <= 0) {
                continue;
            }

            if ($index < $cursor + $chunkSize) {
                return (int) $userId;
            }

            $cursor += $chunkSize;
        }

        return (int) $userIds[$totalUsers - 1];
    }

    private function emptyQueueStatus(): array
    {
        return [
            'total' => 0,
            'analisadas' => 0,
            'pendentes' => 0,
            'abertas' => 0,
            'concluido_em' => null,
            'distribuidor_nome' => null,
        ];
    }

    private function analysisSelectSql(): string
    {
        return "f.id AS familia_id,
                f.residencia_id,
                COALESCE(NULLIF(f.representante_nome, ''), f.responsavel_nome) AS beneficiario_nome,
                COALESCE(NULLIF(f.representante_cpf, ''), f.responsavel_cpf) AS beneficiario_cpf,
                COALESCE(NULLIF(f.representante_rg, ''), f.responsavel_rg) AS beneficiario_rg,
                COALESCE(NULLIF(f.representante_orgao_expedidor, ''), f.responsavel_orgao_expedidor) AS beneficiario_orgao_expedidor,
                COALESCE(NULLIF(f.representante_sexo, ''), f.responsavel_sexo) AS beneficiario_sexo,
                COALESCE(f.representante_data_nascimento, f.data_nascimento) AS beneficiario_data_nascimento,
                f.responsavel_nome,
                f.responsavel_cpf,
                f.responsavel_rg,
                f.responsavel_sexo,
                f.responsavel_orgao_expedidor,
                f.data_nascimento,
                f.telefone,
                f.email,
                f.quantidade_integrantes,
                f.possui_criancas,
                f.possui_idosos,
                f.possui_pcd,
                f.possui_gestantes,
                f.renda_familiar,
                f.perdas_bens_moveis,
                f.situacao_familia,
                f.recebe_beneficio_social,
                f.beneficio_social_nome,
                f.cadastro_concluido,
                f.conclusao_observacoes,
                f.representante_nome,
                f.representante_cpf,
                f.representante_rg,
                f.representante_orgao_expedidor,
                f.representante_data_nascimento,
                f.representante_sexo,
                f.representante_telefone,
                f.representante_email,
                r.id AS residencia_id,
                r.protocolo,
                r.bairro_comunidade,
                r.endereco,
                r.complemento,
                r.imovel,
                r.condicao_residencia,
                r.latitude,
                r.longitude,
                r.quantidade_familias,
                r.data_cadastro,
                a.id AS acao_id,
                a.localidade,
                a.tipo_evento,
                a.data_evento,
                m.nome AS municipio_nome,
                m.uf,
                (
                    SELECT COUNT(*)
                    FROM entregas_ajuda e
                    INNER JOIN tipos_ajuda t ON t.id = e.tipo_ajuda_id
                    WHERE e.familia_id = f.id
                      AND e.deleted_at IS NULL
                      AND " . $this->recomecarTypeSql('t') . "
                ) AS total_entregas,
                (
                    SELECT MAX(COALESCE(e.entregue_em, e.data_entrega))
                    FROM entregas_ajuda e
                    INNER JOIN tipos_ajuda t ON t.id = e.tipo_ajuda_id
                    WHERE e.familia_id = f.id
                      AND e.deleted_at IS NULL
                      AND " . $this->recomecarTypeSql('t') . "
                ) AS ultima_entrega,
                CASE
                    WHEN " . $this->recomecarDeliveredExistsSql() . " THEN 'entregue'
                    WHEN " . $this->recomecarRegisteredExistsSql() . " THEN 'registrado'
                    ELSE 'nao_entregue'
                END AS status_entrega,
                CASE WHEN " . $this->eligibleSql() . " THEN 'apta' ELSE 'inapta' END AS aptidao,
                " . $this->ineligibilityReasonSql() . " AS motivo_inaptidao,
                (
                    SELECT raa.usuario_id
                    FROM recomecar_analise_atribuicoes raa
                    WHERE raa.familia_id = f.id
                    LIMIT 1
                ) AS analista_usuario_id,
                (
                    SELECT raa.concluido_em
                    FROM recomecar_analise_atribuicoes raa
                    WHERE raa.familia_id = f.id
                    LIMIT 1
                ) AS atribuicao_concluida_em,
                (
                    SELECT u_analista.nome
                    FROM recomecar_analise_atribuicoes raa
                    INNER JOIN usuarios u_analista ON u_analista.id = raa.usuario_id
                    WHERE raa.familia_id = f.id
                    LIMIT 1
                ) AS analista_nome,
                (
                    SELECT COUNT(*)
                    FROM documentos_anexos d
                    WHERE d.deleted_at IS NULL
                      AND (d.familia_id = f.id OR (d.residencia_id = r.id AND d.familia_id IS NULL))
                ) AS total_documentos,
                (
                    SELECT COUNT(*)
                    FROM logs_sistema lh
                    WHERE lh.entidade = 'recomecar_analise'
                      AND lh.entidade_id = f.id
                      AND lh.acao IN ('editou_recomecar_dados', 'analisou_recomecar_familia')
                ) AS total_historico,
                (
                    SELECT MAX(la.criado_em)
                    FROM logs_sistema la
                    WHERE la.entidade = 'recomecar_analise'
                      AND la.entidade_id = f.id
                      AND la.acao = 'analisou_recomecar_familia'
                ) AS ultima_analise_em,
                (
                    SELECT ua.nome
                    FROM logs_sistema la
                    LEFT JOIN usuarios ua ON ua.id = la.usuario_id
                    WHERE la.entidade = 'recomecar_analise'
                      AND la.entidade_id = f.id
                      AND la.acao = 'analisou_recomecar_familia'
                    ORDER BY la.criado_em DESC, la.id DESC
                    LIMIT 1
                ) AS ultimo_analista_nome";
    }

    private function analysisExistsSql(): string
    {
        return "EXISTS (
            SELECT 1
            FROM logs_sistema la_status
            WHERE la_status.entidade = 'recomecar_analise'
              AND la_status.entidade_id = f.id
              AND la_status.acao = 'analisou_recomecar_familia'
        )";
    }

    private function eligibleSql(): string
    {
        return "(r.condicao_residencia IS NULL OR r.condicao_residencia <> 'nao_atingida')
            AND (f.renda_familiar IS NULL OR f.renda_familiar <> 'acima_3_salarios')";
    }

    private function ineligibilityReasonSql(): string
    {
        return "COALESCE(CONCAT_WS('; ',
            CASE WHEN r.condicao_residencia = 'nao_atingida' THEN 'Imovel nao atingido' ELSE NULL END,
            CASE WHEN f.renda_familiar = 'acima_3_salarios' THEN 'Renda familiar acima de 3 salarios' ELSE NULL END
        ), '')";
    }

    private function recomecarDeliveryExistsSql(): string
    {
        return 'EXISTS (
            SELECT 1
            FROM entregas_ajuda e_recomecar
            INNER JOIN tipos_ajuda t_recomecar ON t_recomecar.id = e_recomecar.tipo_ajuda_id
            WHERE e_recomecar.familia_id = f.id
              AND e_recomecar.deleted_at IS NULL
              AND ' . $this->recomecarTypeSql('t_recomecar') . '
        )';
    }

    private function recomecarDeliveredExistsSql(): string
    {
        return 'EXISTS (
            SELECT 1
            FROM entregas_ajuda e_recomecar_entregue
            INNER JOIN tipos_ajuda t_recomecar_entregue ON t_recomecar_entregue.id = e_recomecar_entregue.tipo_ajuda_id
            WHERE e_recomecar_entregue.familia_id = f.id
              AND e_recomecar_entregue.deleted_at IS NULL
              AND COALESCE(e_recomecar_entregue.status_operacional, "entregue") = "entregue"
              AND ' . $this->recomecarTypeSql('t_recomecar_entregue') . '
        )';
    }

    private function recomecarRegisteredExistsSql(): string
    {
        return 'EXISTS (
            SELECT 1
            FROM entregas_ajuda e_recomecar_registrado
            INNER JOIN tipos_ajuda t_recomecar_registrado ON t_recomecar_registrado.id = e_recomecar_registrado.tipo_ajuda_id
            WHERE e_recomecar_registrado.familia_id = f.id
              AND e_recomecar_registrado.deleted_at IS NULL
              AND COALESCE(e_recomecar_registrado.status_operacional, "entregue") = "registrado"
              AND ' . $this->recomecarTypeSql('t_recomecar_registrado') . '
        )';
    }

    private function recomecarTypeSql(string $alias): string
    {
        return '(' . $alias . ".nome LIKE '%Programa%Recome%' OR " . $alias . ".nome LIKE 'Recome%')";
    }

    private function ensureAnalysisAssignmentSchema(): void
    {
        Database::connection()->exec(
            "CREATE TABLE IF NOT EXISTS recomecar_analise_atribuicoes (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                familia_id BIGINT UNSIGNED NOT NULL,
                usuario_id BIGINT UNSIGNED NOT NULL,
                atribuido_por BIGINT UNSIGNED NULL,
                estrategia VARCHAR(40) NOT NULL DEFAULT 'periodo',
                filtros_hash CHAR(64) NULL,
                filtros_json TEXT NULL,
                concluido_em DATETIME NULL,
                concluido_por BIGINT UNSIGNED NULL,
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_recomecar_atribuicao_familia FOREIGN KEY (familia_id) REFERENCES familias(id),
                CONSTRAINT fk_recomecar_atribuicao_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
                CONSTRAINT fk_recomecar_atribuicao_autor FOREIGN KEY (atribuido_por) REFERENCES usuarios(id) ON DELETE SET NULL,
                UNIQUE KEY uk_recomecar_atribuicao_familia (familia_id),
                KEY idx_recomecar_atribuicao_usuario (usuario_id),
                KEY idx_recomecar_atribuicao_estrategia (estrategia),
                KEY idx_recomecar_atribuicao_atualizado (atualizado_em)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->ensureColumn('recomecar_analise_atribuicoes', 'concluido_em', 'DATETIME NULL AFTER filtros_json');
        $this->ensureColumn('recomecar_analise_atribuicoes', 'concluido_por', 'BIGINT UNSIGNED NULL AFTER concluido_em');
        $this->ensureIndex('recomecar_analise_atribuicoes', 'idx_recomecar_atribuicao_autor_usuario', '(atribuido_por, usuario_id)');
        $this->ensureIndex('recomecar_analise_atribuicoes', 'idx_recomecar_atribuicao_concluido', '(concluido_em)');
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        $connection = Database::connection();
        $stmt = $connection->prepare(
            'SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND COLUMN_NAME = :column'
        );
        $stmt->bindValue(':table', $table);
        $stmt->bindValue(':column', $column);
        $stmt->execute();

        if ((int) $stmt->fetchColumn() === 0) {
            $connection->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        }
    }

    private function ensureIndex(string $table, string $index, string $definition): void
    {
        $connection = Database::connection();
        $stmt = $connection->prepare(
            'SELECT COUNT(*)
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND INDEX_NAME = :index_name'
        );
        $stmt->bindValue(':table', $table);
        $stmt->bindValue(':index_name', $index);
        $stmt->execute();

        if ((int) $stmt->fetchColumn() === 0) {
            $connection->exec("ALTER TABLE {$table} ADD INDEX {$index} {$definition}");
        }
    }

    private function bind(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue(':' . $key, $value, $type);
        }
    }
}
