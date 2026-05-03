<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class CoassinaturaRepository
{
    private static bool $schemaChecked = false;

    public function __construct()
    {
        $this->ensureSchema();
    }

    public function replacePendingRequests(array $document, array $coSigners): void
    {
        $connection = Database::connection();
        $documentType = (string) ($document['documento_tipo'] ?? '');
        $documentKey = (string) ($document['documento_chave'] ?? '');

        if ($documentType === '' || $documentKey === '') {
            return;
        }

        $connection->beginTransaction();

        try {
            $cancel = $connection->prepare(
                "UPDATE coassinaturas_documentos
                 SET status = 'cancelado', atualizado_em = NOW()
                 WHERE documento_tipo = :documento_tipo
                   AND documento_chave = :documento_chave
                   AND status IN ('pendente', 'autorizado', 'negado')"
            );
            $cancel->bindValue(':documento_tipo', $documentType);
            $cancel->bindValue(':documento_chave', $documentKey);
            $cancel->execute();

            $insert = $connection->prepare(
                'INSERT INTO coassinaturas_documentos
                    (documento_tipo, documento_chave, entidade, entidade_id, titulo, descricao, url_documento,
                     solicitante_usuario_id, coautor_usuario_id, status, payload_json, coautor_snapshot_json,
                     hash_autorizacao, autorizado_em)
                 VALUES
                    (:documento_tipo, :documento_chave, :entidade, :entidade_id, :titulo, :descricao, :url_documento,
                     :solicitante_usuario_id, :coautor_usuario_id, :status, :payload_json, :coautor_snapshot_json,
                     :hash_autorizacao, :autorizado_em)'
            );

            $payloadJson = json_encode($document['payload'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $solicitanteId = (int) ($document['solicitante_usuario_id'] ?? 0);
            $principalSigner = is_array($document['assinante_principal'] ?? null) ? $document['assinante_principal'] : [];
            $principalUserId = (int) ($principalSigner['usuario_id'] ?? $principalSigner['id'] ?? $solicitanteId);
            $solicitanteId = $solicitanteId > 0 ? $solicitanteId : $principalUserId;
            $bindAndExecute = function (array $signer, string $status, ?string $hash, ?string $authorizedAt) use (
                $insert,
                $document,
                $documentType,
                $documentKey,
                $payloadJson,
                $solicitanteId
            ): void {
                $insert->bindValue(':documento_tipo', $documentType);
                $insert->bindValue(':documento_chave', $documentKey);
                $insert->bindValue(':entidade', (string) ($document['entidade'] ?? 'documentos'));
                $insert->bindValue(':entidade_id', (int) ($document['entidade_id'] ?? 0), PDO::PARAM_INT);
                $insert->bindValue(':titulo', mb_substr((string) ($document['titulo'] ?? 'Documento para assinatura'), 0, 180));
                $insert->bindValue(':descricao', (string) ($document['descricao'] ?? ''));
                $insert->bindValue(':url_documento', mb_substr((string) ($document['url_documento'] ?? ''), 0, 255));
                $insert->bindValue(':solicitante_usuario_id', $solicitanteId, PDO::PARAM_INT);
                $insert->bindValue(':coautor_usuario_id', (int) ($signer['usuario_id'] ?? $signer['id'] ?? 0), PDO::PARAM_INT);
                $insert->bindValue(':status', $status);
                $insert->bindValue(':payload_json', $payloadJson);
                $insert->bindValue(':coautor_snapshot_json', json_encode($this->userSnapshot($signer), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

                if ($hash !== null && $hash !== '') {
                    $insert->bindValue(':hash_autorizacao', mb_substr($hash, 0, 64));
                } else {
                    $insert->bindValue(':hash_autorizacao', null, PDO::PARAM_NULL);
                }

                if ($authorizedAt !== null && $authorizedAt !== '') {
                    $insert->bindValue(':autorizado_em', $authorizedAt);
                } else {
                    $insert->bindValue(':autorizado_em', null, PDO::PARAM_NULL);
                }

                $insert->execute();
            };

            if ($principalUserId > 0) {
                $principalSigner['usuario_id'] = $principalUserId;
                $principalSigner['tipo'] = 'assinante_principal';
                $bindAndExecute(
                    $principalSigner,
                    'autorizado',
                    (string) ($principalSigner['hash'] ?? $document['hash_autorizacao'] ?? ($document['payload']['hash'] ?? '')),
                    (string) ($principalSigner['signed_at'] ?? $document['assinado_em'] ?? date('Y-m-d H:i:s'))
                );
            }

            foreach ($coSigners as $coSigner) {
                $coSignerId = (int) ($coSigner['usuario_id'] ?? $coSigner['id'] ?? 0);
                if ($coSignerId <= 0 || $coSignerId === $principalUserId) {
                    continue;
                }

                $bindAndExecute($coSigner, 'pendente', null, null);
            }

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    public function cancelDocument(string $documentType, string $documentKey): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE coassinaturas_documentos
             SET status = 'cancelado', atualizado_em = NOW()
             WHERE documento_tipo = :documento_tipo
               AND documento_chave = :documento_chave
               AND status IN ('pendente', 'autorizado', 'negado')"
        );
        $stmt->bindValue(':documento_tipo', $documentType);
        $stmt->bindValue(':documento_chave', $documentKey);
        $stmt->execute();
    }

    public function cancelCoSignatures(string $documentType, string $documentKey, array $requestIds, int $principalUserId): int
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): int => (int) $id,
            $requestIds
        ), static fn (int $id): bool => $id > 0)));

        if ($documentType === '' || $documentKey === '' || $ids === [] || $principalUserId <= 0) {
            return 0;
        }

        $placeholders = [];
        foreach ($ids as $index => $id) {
            $placeholders[] = ':request_id_' . $index;
        }

        $stmt = Database::connection()->prepare(
            "UPDATE coassinaturas_documentos
             SET status = 'cancelado', atualizado_em = NOW()
             WHERE documento_tipo = :documento_tipo
               AND documento_chave = :documento_chave
               AND solicitante_usuario_id = :principal_usuario_id
               AND coautor_usuario_id <> solicitante_usuario_id
               AND status IN ('pendente', 'autorizado', 'negado')
               AND id IN (" . implode(', ', $placeholders) . ')'
        );
        $stmt->bindValue(':documento_tipo', $documentType);
        $stmt->bindValue(':documento_chave', $documentKey);
        $stmt->bindValue(':principal_usuario_id', $principalUserId, PDO::PARAM_INT);

        foreach ($ids as $index => $id) {
            $stmt->bindValue(':request_id_' . $index, $id, PDO::PARAM_INT);
        }

        $stmt->execute();

        return $stmt->rowCount();
    }

    public function activeByDocument(string $documentType, string $documentKey): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT c.*, u.nome AS coautor_nome, u.cpf AS coautor_cpf, u.email AS coautor_email,
                    u.telefone AS coautor_telefone, u.graduacao AS coautor_graduacao,
                    u.nome_guerra AS coautor_nome_guerra, u.matricula_funcional AS coautor_matricula_funcional,
                    u.orgao AS coautor_orgao, u.unidade_setor AS coautor_unidade_setor,
                    s.nome AS solicitante_nome
             FROM coassinaturas_documentos c
             JOIN usuarios u ON u.id = c.coautor_usuario_id
             JOIN usuarios s ON s.id = c.solicitante_usuario_id
             WHERE c.documento_tipo = :documento_tipo
               AND c.documento_chave = :documento_chave
               AND c.status IN (\'pendente\', \'autorizado\', \'negado\')
             ORDER BY c.id ASC'
        );
        $stmt->bindValue(':documento_tipo', $documentType);
        $stmt->bindValue(':documento_chave', $documentKey);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function pendingForUser(int $userId): array
    {
        return $this->forUserByStatus($userId, ['pendente']);
    }

    public function allForUser(int $userId): array
    {
        return $this->forUserByStatus($userId, ['pendente', 'autorizado', 'negado']);
    }

    public function requestedByUser(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT c.*, u.nome AS coautor_nome, u.cpf AS coautor_cpf
             FROM coassinaturas_documentos c
             JOIN usuarios u ON u.id = c.coautor_usuario_id
             WHERE c.solicitante_usuario_id = :usuario_id
               AND c.status IN (\'pendente\', \'autorizado\', \'negado\')
             ORDER BY c.atualizado_em DESC, c.solicitado_em DESC, c.id DESC
             LIMIT 80'
        );
        $stmt->bindValue(':usuario_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function repairPrincipalHistoryFromAuditLogs(int $userId, bool $includeAll = false): void
    {
        if ($userId <= 0 && !$includeAll) {
            return;
        }

        $userFilter = $includeAll ? '' : 'AND l.usuario_id = :usuario_id';
        $stmt = Database::connection()->prepare(
            "SELECT l.id, l.usuario_id, l.acao, l.entidade, l.entidade_id, l.descricao, l.criado_em,
                    u.nome, u.cpf, u.email, u.telefone, u.graduacao, u.nome_guerra,
                    u.matricula_funcional, u.orgao, u.unidade_setor
             FROM logs_sistema l
             LEFT JOIN usuarios u ON u.id = l.usuario_id
             WHERE l.acao IN ('assinou_dti', 'assinou_prestacao_contas')
               AND l.usuario_id IS NOT NULL
               {$userFilter}
               AND NOT EXISTS (
                    SELECT 1
                    FROM logs_sistema r
                    WHERE r.entidade = l.entidade
                      AND r.entidade_id = l.entidade_id
                      AND (
                        (l.acao = 'assinou_dti' AND r.acao = 'removeu_assinatura_dti')
                        OR (l.acao = 'assinou_prestacao_contas' AND r.acao = 'removeu_assinatura_prestacao_contas')
                      )
                      AND (r.criado_em > l.criado_em OR (r.criado_em = l.criado_em AND r.id > l.id))
               )
             ORDER BY l.criado_em DESC, l.id DESC
             LIMIT 500"
        );

        if (!$includeAll) {
            $stmt->bindValue(':usuario_id', $userId, PDO::PARAM_INT);
        }

        $stmt->execute();

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $log) {
            $this->repairPrincipalHistoryFromLog($log);
        }
    }

    public function summaryForUser(int $userId, bool $includeAll = false): array
    {
        if ($includeAll) {
            $stmt = Database::connection()->query(
                "SELECT
                    COUNT(*) AS total_sistema,
                    SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) AS pendentes_sistema,
                    SUM(CASE WHEN status = 'autorizado' THEN 1 ELSE 0 END) AS autorizadas,
                    SUM(CASE WHEN status = 'negado' THEN 1 ELSE 0 END) AS negadas
                 FROM coassinaturas_documentos
                 WHERE status IN ('pendente', 'autorizado', 'negado')"
            );
            $summary = $stmt !== false ? ($stmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];

            return [
                'total_sistema' => (int) ($summary['total_sistema'] ?? 0),
                'pendentes_sistema' => (int) ($summary['pendentes_sistema'] ?? 0),
                'para_mim_total' => 0,
                'para_mim_pendentes' => 0,
                'solicitadas_total' => 0,
                'solicitadas_pendentes' => 0,
                'autorizadas' => (int) ($summary['autorizadas'] ?? 0),
                'negadas' => (int) ($summary['negadas'] ?? 0),
            ];
        }

        $stmt = Database::connection()->prepare(
            "SELECT
                SUM(CASE WHEN coautor_usuario_id = :coautor_total_id AND coautor_usuario_id <> solicitante_usuario_id THEN 1 ELSE 0 END) AS para_mim_total,
                SUM(CASE WHEN coautor_usuario_id = :coautor_pendente_id AND coautor_usuario_id <> solicitante_usuario_id AND status = 'pendente' THEN 1 ELSE 0 END) AS para_mim_pendentes,
                SUM(CASE WHEN solicitante_usuario_id = :solicitante_total_id THEN 1 ELSE 0 END) AS solicitadas_total,
                SUM(CASE WHEN solicitante_usuario_id = :solicitante_pendente_id AND status = 'pendente' THEN 1 ELSE 0 END) AS solicitadas_pendentes,
                SUM(CASE WHEN (coautor_usuario_id = :coautor_autorizado_id OR solicitante_usuario_id = :solicitante_autorizado_id) AND status = 'autorizado' THEN 1 ELSE 0 END) AS autorizadas,
                SUM(CASE WHEN (coautor_usuario_id = :coautor_negado_id OR solicitante_usuario_id = :solicitante_negado_id) AND status = 'negado' THEN 1 ELSE 0 END) AS negadas
             FROM coassinaturas_documentos
             WHERE status IN ('pendente', 'autorizado', 'negado')
               AND (coautor_usuario_id = :coautor_scope_id OR solicitante_usuario_id = :solicitante_scope_id)"
        );
        foreach ([
            ':coautor_total_id',
            ':coautor_pendente_id',
            ':solicitante_total_id',
            ':solicitante_pendente_id',
            ':coautor_autorizado_id',
            ':solicitante_autorizado_id',
            ':coautor_negado_id',
            ':solicitante_negado_id',
            ':coautor_scope_id',
            ':solicitante_scope_id',
        ] as $param) {
            $stmt->bindValue($param, $userId, PDO::PARAM_INT);
        }
        $stmt->execute();

        $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'para_mim_total' => (int) ($summary['para_mim_total'] ?? 0),
            'para_mim_pendentes' => (int) ($summary['para_mim_pendentes'] ?? 0),
            'solicitadas_total' => (int) ($summary['solicitadas_total'] ?? 0),
            'solicitadas_pendentes' => (int) ($summary['solicitadas_pendentes'] ?? 0),
            'total_sistema' => 0,
            'pendentes_sistema' => 0,
            'autorizadas' => (int) ($summary['autorizadas'] ?? 0),
            'negadas' => (int) ($summary['negadas'] ?? 0),
        ];
    }

    public function paginatedForUser(int $userId, array $filters, int $page = 1, int $perPage = 10, bool $includeAll = false): array
    {
        $perPage = min(10, max(1, $perPage));
        $page = max(1, $page);
        [$whereSql, $params] = $this->signatureSearchWhere($userId, $filters, $includeAll);

        $groupedSelect = "SELECT
                c.documento_tipo,
                c.documento_chave,
                COALESCE(
                    MIN(CASE WHEN c.coautor_usuario_id = :group_user_action_id AND c.coautor_usuario_id <> c.solicitante_usuario_id THEN c.id END),
                    MIN(CASE WHEN c.coautor_usuario_id = c.solicitante_usuario_id THEN c.id END),
                    MIN(c.id)
                ) AS representative_id,
                COUNT(*) AS total_assinaturas,
                SUM(CASE WHEN c.status = 'pendente' THEN 1 ELSE 0 END) AS pendentes_count,
                SUM(CASE WHEN c.status = 'autorizado' THEN 1 ELSE 0 END) AS autorizados_count,
                SUM(CASE WHEN c.status = 'negado' THEN 1 ELSE 0 END) AS negados_count,
                GROUP_CONCAT(
                    DISTINCT CASE WHEN c.coautor_usuario_id <> c.solicitante_usuario_id THEN u.nome ELSE NULL END
                    ORDER BY u.nome SEPARATOR ', '
                ) AS coautores_nomes,
                MAX(COALESCE(c.atualizado_em, c.solicitado_em)) AS ultima_atualizacao,
                MIN(c.solicitado_em) AS primeira_solicitacao
             FROM coassinaturas_documentos c
             JOIN usuarios u ON u.id = c.coautor_usuario_id
             JOIN usuarios s ON s.id = c.solicitante_usuario_id
             " . $whereSql . "
             GROUP BY c.documento_tipo, c.documento_chave";

        $countStmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM (' . $groupedSelect . ') grouped_documents'
        );
        $countStmt->bindValue(':group_user_action_id', $userId, PDO::PARAM_INT);
        $this->bindSearchParams($countStmt, $params);
        $countStmt->execute();

        $total = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $stmt = Database::connection()->prepare(
            "SELECT
                    c.id,
                    c.documento_tipo,
                    c.documento_chave,
                    c.entidade,
                    c.entidade_id,
                    c.titulo,
                    c.descricao,
                    c.url_documento,
                    c.solicitante_usuario_id,
                    c.coautor_usuario_id,
                    CASE
                        WHEN grouped.negados_count > 0 THEN 'negado'
                        WHEN grouped.pendentes_count > 0 THEN 'pendente'
                        ELSE 'autorizado'
                    END AS status,
                    c.payload_json,
                    c.coautor_snapshot_json,
                    c.hash_autorizacao,
                    c.motivo_negativa,
                    grouped.primeira_solicitacao AS solicitado_em,
                    c.autorizado_em,
                    c.negado_em,
                    c.solicitante_notificado_em,
                    grouped.ultima_atualizacao AS atualizado_em,
                    grouped.total_assinaturas,
                    grouped.pendentes_count,
                    grouped.autorizados_count,
                    grouped.negados_count,
                    grouped.coautores_nomes,
                    u.nome AS coautor_nome,
                    u.cpf AS coautor_cpf,
                    s.nome AS solicitante_nome, s.cpf AS solicitante_cpf,
                    CASE
                        WHEN c.coautor_usuario_id = c.solicitante_usuario_id THEN 'assinante_principal'
                        WHEN c.coautor_usuario_id = :vinculo_usuario_id THEN 'para_mim'
                        ELSE 'solicitada'
                    END AS vinculo
             FROM (" . $groupedSelect . ") grouped
             JOIN coassinaturas_documentos c ON c.id = grouped.representative_id
             JOIN usuarios u ON u.id = c.coautor_usuario_id
             JOIN usuarios s ON s.id = c.solicitante_usuario_id
             ORDER BY
                CASE
                    WHEN grouped.pendentes_count > 0 THEN 0
                    WHEN grouped.negados_count > 0 THEN 1
                    ELSE 2
                END ASC,
                grouped.ultima_atualizacao DESC,
                c.id DESC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':vinculo_usuario_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':group_user_action_id', $userId, PDO::PARAM_INT);
        $this->bindSearchParams($stmt, $params);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ];
    }

    public function findForUser(int $id, int $userId, bool $includeAll = false): ?array
    {
        $accessSql = $includeAll
            ? ''
            : 'AND (c.coautor_usuario_id = :coautor_usuario_id OR c.solicitante_usuario_id = :solicitante_usuario_id)';
        $stmt = Database::connection()->prepare(
            'SELECT c.*, u.nome AS coautor_nome, u.cpf AS coautor_cpf, u.email AS coautor_email,
                    s.nome AS solicitante_nome, s.cpf AS solicitante_cpf
             FROM coassinaturas_documentos c
             JOIN usuarios u ON u.id = c.coautor_usuario_id
             JOIN usuarios s ON s.id = c.solicitante_usuario_id
             WHERE c.id = :id
               ' . $accessSql . '
             LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        if (!$includeAll) {
            $stmt->bindValue(':coautor_usuario_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':solicitante_usuario_id', $userId, PDO::PARAM_INT);
        }
        $stmt->execute();

        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($request) ? $request : null;
    }

    public function canUserAccessDocument(string $documentType, string $documentKey, int $userId, bool $includeAll = false): bool
    {
        if ($includeAll) {
            return true;
        }

        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*)
             FROM coassinaturas_documentos
             WHERE documento_tipo = :documento_tipo
               AND documento_chave = :documento_chave
               AND status IN ('pendente', 'autorizado', 'negado')
               AND (coautor_usuario_id = :coautor_usuario_id OR solicitante_usuario_id = :solicitante_usuario_id)"
        );
        $stmt->bindValue(':documento_tipo', $documentType);
        $stmt->bindValue(':documento_chave', $documentKey);
        $stmt->bindValue(':coautor_usuario_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':solicitante_usuario_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn() > 0;
    }

    public function authorize(int $id, int $userId, array $user): bool
    {
        $stmt = Database::connection()->prepare(
            "UPDATE coassinaturas_documentos
             SET status = 'autorizado',
                 autorizado_em = NOW(),
                 atualizado_em = NOW(),
                 hash_autorizacao = :hash_autorizacao,
                 coautor_snapshot_json = :coautor_snapshot_json,
                 solicitante_notificado_em = NULL
             WHERE id = :id
               AND coautor_usuario_id = :usuario_id
               AND status = 'pendente'"
        );
        $stmt->bindValue(':hash_autorizacao', strtoupper(hash('sha256', $id . '|' . $userId . '|' . date('Y-m-d H:i:s'))));
        $stmt->bindValue(':coautor_snapshot_json', json_encode($this->userSnapshot($user), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':usuario_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public function reject(int $id, int $userId, string $reason): bool
    {
        $stmt = Database::connection()->prepare(
            "UPDATE coassinaturas_documentos
             SET status = 'negado',
                 negado_em = NOW(),
                 atualizado_em = NOW(),
                 motivo_negativa = :motivo_negativa,
                 solicitante_notificado_em = NULL
             WHERE id = :id
               AND coautor_usuario_id = :usuario_id
               AND status = 'pendente'"
        );
        $stmt->bindValue(':motivo_negativa', mb_substr($reason, 0, 500));
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':usuario_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public function returnToSignature(int $id, int $userId): bool
    {
        $stmt = Database::connection()->prepare(
            "UPDATE coassinaturas_documentos
             SET status = 'pendente',
                 negado_em = NULL,
                 motivo_negativa = NULL,
                 atualizado_em = NOW(),
                 solicitante_notificado_em = NULL
             WHERE id = :id
               AND coautor_usuario_id = :usuario_id
               AND status = 'negado'"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':usuario_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public function pendingCountForUser(int $userId): int
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*)
             FROM coassinaturas_documentos
             WHERE coautor_usuario_id = :usuario_id
               AND status = 'pendente'"
        );
        $stmt->bindValue(':usuario_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function pendingRequesterNoticeCount(int $userId): int
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*)
             FROM coassinaturas_documentos
             WHERE solicitante_usuario_id = :usuario_id
               AND status IN ('autorizado', 'negado')
               AND solicitante_notificado_em IS NULL
               AND coautor_usuario_id <> solicitante_usuario_id"
        );
        $stmt->bindValue(':usuario_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function pendingRequestedByUserCount(int $userId): int
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*)
             FROM coassinaturas_documentos
             WHERE solicitante_usuario_id = :usuario_id
               AND status = 'pendente'"
        );
        $stmt->bindValue(':usuario_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function markRequesterNotified(int $userId): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE coassinaturas_documentos
             SET solicitante_notificado_em = NOW()
             WHERE solicitante_usuario_id = :usuario_id
               AND status IN ('autorizado', 'negado')
               AND solicitante_notificado_em IS NULL
               AND coautor_usuario_id <> solicitante_usuario_id"
        );
        $stmt->bindValue(':usuario_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function statusSummary(string $documentType, string $documentKey): array
    {
        $requests = $this->activeByDocument($documentType, $documentKey);
        $summary = [
            'total' => count($requests),
            'pendentes' => 0,
            'autorizados' => 0,
            'negados' => 0,
            'impressao_liberada' => true,
            'solicitacoes' => $requests,
        ];

        foreach ($requests as $request) {
            $status = (string) ($request['status'] ?? '');

            if ($status === 'pendente') {
                $summary['pendentes']++;
            } elseif ($status === 'autorizado') {
                $summary['autorizados']++;
            } elseif ($status === 'negado') {
                $summary['negados']++;
            }
        }

        $summary['impressao_liberada'] = $summary['pendentes'] === 0 && $summary['negados'] === 0;

        return $summary;
    }

    public function authorizedSignerPayloads(string $documentType, string $documentKey): array
    {
        $payloads = [];

        foreach ($this->activeByDocument($documentType, $documentKey) as $request) {
            if ((string) ($request['status'] ?? '') !== 'autorizado') {
                continue;
            }

            if ((int) ($request['coautor_usuario_id'] ?? 0) === (int) ($request['solicitante_usuario_id'] ?? 0)) {
                continue;
            }

            $snapshot = json_decode((string) ($request['coautor_snapshot_json'] ?? ''), true);
            $payloads[] = is_array($snapshot) ? $snapshot : $this->userSnapshot([
                'id' => $request['coautor_usuario_id'] ?? 0,
                'nome' => $request['coautor_nome'] ?? '',
                'cpf' => $request['coautor_cpf'] ?? '',
                'email' => $request['coautor_email'] ?? '',
                'graduacao' => $request['coautor_graduacao'] ?? '',
                'nome_guerra' => $request['coautor_nome_guerra'] ?? '',
                'matricula_funcional' => $request['coautor_matricula_funcional'] ?? '',
                'orgao' => $request['coautor_orgao'] ?? '',
                'unidade_setor' => $request['coautor_unidade_setor'] ?? '',
            ]);
        }

        return $payloads;
    }

    private function forUserByStatus(int $userId, array $statuses): array
    {
        $placeholders = [];
        foreach ($statuses as $index => $status) {
            $placeholders[] = ':status_' . $index;
        }

        $stmt = Database::connection()->prepare(
            'SELECT c.*, s.nome AS solicitante_nome, s.cpf AS solicitante_cpf
             FROM coassinaturas_documentos c
             JOIN usuarios s ON s.id = c.solicitante_usuario_id
             WHERE c.coautor_usuario_id = :usuario_id
               AND c.status IN (' . implode(', ', $placeholders) . ')
             ORDER BY c.solicitado_em DESC, c.id DESC'
        );
        $stmt->bindValue(':usuario_id', $userId, PDO::PARAM_INT);

        foreach ($statuses as $index => $status) {
            $stmt->bindValue(':status_' . $index, $status);
        }

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function signatureSearchWhere(int $userId, array $filters, bool $includeAll = false): array
    {
        $where = [
            "c.status IN ('pendente', 'autorizado', 'negado')",
        ];
        $params = [];

        $scope = (string) ($filters['escopo'] ?? 'todas');
        if ($scope === 'para_mim') {
            $where[] = 'c.coautor_usuario_id = :scope_coautor_id';
            $where[] = 'c.coautor_usuario_id <> c.solicitante_usuario_id';
            $params[':scope_coautor_id'] = [$userId, PDO::PARAM_INT];
        } elseif ($scope === 'solicitadas') {
            $where[] = 'c.solicitante_usuario_id = :scope_solicitante_id';
            $params[':scope_solicitante_id'] = [$userId, PDO::PARAM_INT];
        } elseif (!$includeAll) {
            $where[] = '(c.coautor_usuario_id = :scope_coautor_id OR c.solicitante_usuario_id = :scope_solicitante_id)';
            $params[':scope_coautor_id'] = [$userId, PDO::PARAM_INT];
            $params[':scope_solicitante_id'] = [$userId, PDO::PARAM_INT];
        }

        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, ['pendente', 'autorizado', 'negado'], true)) {
            $where[] = 'c.status = :status';
            $params[':status'] = [$status, PDO::PARAM_STR];
        }

        $documentType = (string) ($filters['documento_tipo'] ?? '');
        if (in_array($documentType, ['dti', 'prestacao_contas', 'recomecar'], true)) {
            $where[] = 'c.documento_tipo = :documento_tipo';
            $params[':documento_tipo'] = [$documentType, PDO::PARAM_STR];
        }

        $dateStart = (string) ($filters['data_inicio'] ?? '');
        if ($dateStart !== '') {
            $where[] = 'DATE(c.solicitado_em) >= :data_inicio';
            $params[':data_inicio'] = [$dateStart, PDO::PARAM_STR];
        }

        $dateEnd = (string) ($filters['data_fim'] ?? '');
        if ($dateEnd !== '') {
            $where[] = 'DATE(c.solicitado_em) <= :data_fim';
            $params[':data_fim'] = [$dateEnd, PDO::PARAM_STR];
        }

        $search = trim((string) ($filters['busca'] ?? ''));
        if ($search !== '') {
            $where[] = '(c.titulo LIKE :busca_titulo OR c.descricao LIKE :busca_descricao OR c.documento_chave LIKE :busca_chave OR u.nome LIKE :busca_coautor OR s.nome LIKE :busca_solicitante)';
            foreach ([':busca_titulo', ':busca_descricao', ':busca_chave', ':busca_coautor', ':busca_solicitante'] as $param) {
                $params[$param] = ['%' . $search . '%', PDO::PARAM_STR];
            }
        }

        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    private function bindSearchParams(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $name => [$value, $type]) {
            $stmt->bindValue($name, $value, $type);
        }
    }

    private function repairPrincipalHistoryFromLog(array $log): void
    {
        $decoded = json_decode((string) ($log['descricao'] ?? ''), true);

        if (!is_array($decoded)) {
            return;
        }

        $documentType = (string) ($log['acao'] ?? '') === 'assinou_dti' ? 'dti' : 'prestacao_contas';
        $documentKey = $documentType === 'dti'
            ? 'dti:' . (int) ($log['entidade_id'] ?? 0)
            : (string) ($decoded['document_key'] ?? '');

        if ($documentKey === '' || (int) ($log['usuario_id'] ?? 0) <= 0) {
            return;
        }

        if ($this->principalHistoryExists($documentType, $documentKey, (int) $log['usuario_id'])) {
            return;
        }

        $filters = is_array($decoded['filters'] ?? null) ? $decoded['filters'] : [];
        $url = $documentType === 'dti'
            ? '/cadastros/residencias/' . (int) ($log['entidade_id'] ?? 0) . '/dti'
            : '/gestor/prestacao-contas?' . http_build_query(array_filter($filters, static fn (mixed $value): bool => (string) $value !== '') + ['assinatura' => '1']);

        $this->insertPrincipalHistory([
            'documento_tipo' => $documentType,
            'documento_chave' => $documentKey,
            'entidade' => $documentType === 'dti' ? 'residencias' : 'prestacao_contas',
            'entidade_id' => (int) ($log['entidade_id'] ?? 0),
            'titulo' => $documentType === 'dti'
                ? 'DTI ' . (string) ($decoded['protocolo'] ?? '')
                : 'Prestação de contas de ajuda humanitária',
            'descricao' => $documentType === 'dti'
                ? 'Documento DTI assinado pelo usuário principal.'
                : 'Documento de prestação de contas assinado pelo usuário principal.',
            'url_documento' => $url,
            'solicitante_usuario_id' => (int) $log['usuario_id'],
            'payload' => [
                'documento' => $decoded['documento'] ?? ($documentType === 'dti' ? 'DTI' : 'Prestação de contas'),
                'document_key' => $decoded['document_key'] ?? $documentKey,
                'protocolo' => $decoded['protocolo'] ?? '',
                'hash' => $decoded['hash'] ?? '',
                'filters' => $filters,
            ],
            'assinante_principal' => [
                'usuario_id' => (int) $log['usuario_id'],
                'tipo' => 'assinante_principal',
                'nome' => (string) ($decoded['nome'] ?? $log['nome'] ?? ''),
                'cpf' => (string) ($decoded['cpf'] ?? $log['cpf'] ?? ''),
                'email' => (string) ($decoded['email'] ?? $log['email'] ?? ''),
                'telefone' => (string) ($decoded['telefone'] ?? $log['telefone'] ?? ''),
                'graduacao' => (string) ($decoded['graduacao'] ?? $log['graduacao'] ?? ''),
                'nome_guerra' => (string) ($decoded['nome_guerra'] ?? $log['nome_guerra'] ?? ''),
                'matricula_funcional' => (string) ($decoded['matricula_funcional'] ?? $log['matricula_funcional'] ?? ''),
                'orgao' => (string) ($decoded['orgao'] ?? $log['orgao'] ?? ''),
                'unidade_setor' => (string) ($decoded['unidade_setor'] ?? $log['unidade_setor'] ?? ''),
                'signed_at' => (string) ($decoded['signed_at'] ?? $log['criado_em'] ?? ''),
                'hash' => (string) ($decoded['hash'] ?? ''),
            ],
        ]);
    }

    private function principalHistoryExists(string $documentType, string $documentKey, int $userId): bool
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*)
             FROM coassinaturas_documentos
             WHERE documento_tipo = :documento_tipo
               AND documento_chave = :documento_chave
               AND solicitante_usuario_id = :solicitante_usuario_id
               AND coautor_usuario_id = :coautor_usuario_id
               AND status IN ('pendente', 'autorizado', 'negado')"
        );
        $stmt->bindValue(':documento_tipo', $documentType);
        $stmt->bindValue(':documento_chave', $documentKey);
        $stmt->bindValue(':solicitante_usuario_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':coautor_usuario_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn() > 0;
    }

    private function insertPrincipalHistory(array $document): void
    {
        $principalSigner = is_array($document['assinante_principal'] ?? null) ? $document['assinante_principal'] : [];
        $principalUserId = (int) ($principalSigner['usuario_id'] ?? $principalSigner['id'] ?? $document['solicitante_usuario_id'] ?? 0);

        if ($principalUserId <= 0) {
            return;
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO coassinaturas_documentos
                (documento_tipo, documento_chave, entidade, entidade_id, titulo, descricao, url_documento,
                 solicitante_usuario_id, coautor_usuario_id, status, payload_json, coautor_snapshot_json,
                 hash_autorizacao, autorizado_em)
             VALUES
                (:documento_tipo, :documento_chave, :entidade, :entidade_id, :titulo, :descricao, :url_documento,
                 :solicitante_usuario_id, :coautor_usuario_id, \'autorizado\', :payload_json, :coautor_snapshot_json,
                 :hash_autorizacao, :autorizado_em)'
        );
        $stmt->bindValue(':documento_tipo', (string) ($document['documento_tipo'] ?? ''));
        $stmt->bindValue(':documento_chave', (string) ($document['documento_chave'] ?? ''));
        $stmt->bindValue(':entidade', (string) ($document['entidade'] ?? 'documentos'));
        $stmt->bindValue(':entidade_id', (int) ($document['entidade_id'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':titulo', mb_substr((string) ($document['titulo'] ?? 'Documento assinado'), 0, 180));
        $stmt->bindValue(':descricao', (string) ($document['descricao'] ?? ''));
        $stmt->bindValue(':url_documento', mb_substr((string) ($document['url_documento'] ?? ''), 0, 255));
        $stmt->bindValue(':solicitante_usuario_id', $principalUserId, PDO::PARAM_INT);
        $stmt->bindValue(':coautor_usuario_id', $principalUserId, PDO::PARAM_INT);
        $stmt->bindValue(':payload_json', json_encode($document['payload'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $stmt->bindValue(':coautor_snapshot_json', json_encode($this->userSnapshot($principalSigner), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $hash = (string) ($principalSigner['hash'] ?? $document['hash_autorizacao'] ?? ($document['payload']['hash'] ?? ''));
        if ($hash !== '') {
            $stmt->bindValue(':hash_autorizacao', mb_substr($hash, 0, 64));
        } else {
            $stmt->bindValue(':hash_autorizacao', null, PDO::PARAM_NULL);
        }

        $authorizedAt = (string) ($principalSigner['signed_at'] ?? $document['assinado_em'] ?? date('Y-m-d H:i:s'));
        $stmt->bindValue(':autorizado_em', $authorizedAt !== '' ? $authorizedAt : date('Y-m-d H:i:s'));
        $stmt->execute();
    }

    private function userSnapshot(array $user): array
    {
        return [
            'usuario_id' => (int) ($user['id'] ?? $user['usuario_id'] ?? 0),
            'tipo' => (string) ($user['tipo'] ?? 'coassinante'),
            'nome' => (string) ($user['nome'] ?? ''),
            'cpf' => (string) ($user['cpf'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'telefone' => (string) ($user['telefone'] ?? ''),
            'graduacao' => (string) ($user['graduacao'] ?? ''),
            'nome_guerra' => (string) ($user['nome_guerra'] ?? ''),
            'matricula_funcional' => (string) ($user['matricula_funcional'] ?? ''),
            'orgao' => (string) ($user['orgao'] ?? ''),
            'unidade_setor' => (string) ($user['unidade_setor'] ?? ''),
        ];
    }

    private function ensureSchema(): void
    {
        if (self::$schemaChecked) {
            return;
        }

        Database::connection()->exec(
            "CREATE TABLE IF NOT EXISTS coassinaturas_documentos (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                documento_tipo VARCHAR(60) NOT NULL,
                documento_chave VARCHAR(128) NOT NULL,
                entidade VARCHAR(100) NOT NULL,
                entidade_id BIGINT UNSIGNED NOT NULL,
                titulo VARCHAR(180) NOT NULL,
                descricao TEXT NULL,
                url_documento VARCHAR(255) NOT NULL,
                solicitante_usuario_id BIGINT UNSIGNED NOT NULL,
                coautor_usuario_id BIGINT UNSIGNED NOT NULL,
                status ENUM('pendente', 'autorizado', 'negado', 'cancelado') NOT NULL DEFAULT 'pendente',
                payload_json LONGTEXT NULL,
                coautor_snapshot_json TEXT NULL,
                hash_autorizacao CHAR(64) NULL,
                motivo_negativa VARCHAR(500) NULL,
                solicitado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                autorizado_em DATETIME NULL,
                negado_em DATETIME NULL,
                solicitante_notificado_em DATETIME NULL,
                atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_coassinaturas_coautor_status (coautor_usuario_id, status),
                KEY idx_coassinaturas_solicitante_status (solicitante_usuario_id, status),
                KEY idx_coassinaturas_documento (documento_tipo, documento_chave),
                CONSTRAINT fk_coassinaturas_solicitante FOREIGN KEY (solicitante_usuario_id) REFERENCES usuarios(id),
                CONSTRAINT fk_coassinaturas_coautor FOREIGN KEY (coautor_usuario_id) REFERENCES usuarios(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        self::$schemaChecked = true;
    }
}
