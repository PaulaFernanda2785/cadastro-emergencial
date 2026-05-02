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

            if ($coSigners !== []) {
                $insert = $connection->prepare(
                    'INSERT INTO coassinaturas_documentos
                        (documento_tipo, documento_chave, entidade, entidade_id, titulo, descricao, url_documento,
                         solicitante_usuario_id, coautor_usuario_id, status, payload_json, coautor_snapshot_json)
                     VALUES
                        (:documento_tipo, :documento_chave, :entidade, :entidade_id, :titulo, :descricao, :url_documento,
                         :solicitante_usuario_id, :coautor_usuario_id, \'pendente\', :payload_json, :coautor_snapshot_json)'
                );

                foreach ($coSigners as $coSigner) {
                    $insert->bindValue(':documento_tipo', $documentType);
                    $insert->bindValue(':documento_chave', $documentKey);
                    $insert->bindValue(':entidade', (string) ($document['entidade'] ?? 'documentos'));
                    $insert->bindValue(':entidade_id', (int) ($document['entidade_id'] ?? 0), PDO::PARAM_INT);
                    $insert->bindValue(':titulo', mb_substr((string) ($document['titulo'] ?? 'Documento para assinatura'), 0, 180));
                    $insert->bindValue(':descricao', (string) ($document['descricao'] ?? ''));
                    $insert->bindValue(':url_documento', mb_substr((string) ($document['url_documento'] ?? ''), 0, 255));
                    $insert->bindValue(':solicitante_usuario_id', (int) ($document['solicitante_usuario_id'] ?? 0), PDO::PARAM_INT);
                    $insert->bindValue(':coautor_usuario_id', (int) ($coSigner['id'] ?? 0), PDO::PARAM_INT);
                    $insert->bindValue(':payload_json', json_encode($document['payload'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                    $insert->bindValue(':coautor_snapshot_json', json_encode($this->userSnapshot($coSigner), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                    $insert->execute();
                }
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

    public function findForUser(int $id, int $userId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT c.*, u.nome AS coautor_nome, u.cpf AS coautor_cpf, u.email AS coautor_email,
                    s.nome AS solicitante_nome, s.cpf AS solicitante_cpf
             FROM coassinaturas_documentos c
             JOIN usuarios u ON u.id = c.coautor_usuario_id
             JOIN usuarios s ON s.id = c.solicitante_usuario_id
             WHERE c.id = :id
               AND (c.coautor_usuario_id = :coautor_usuario_id OR c.solicitante_usuario_id = :solicitante_usuario_id)
             LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':coautor_usuario_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':solicitante_usuario_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($request) ? $request : null;
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
               AND solicitante_notificado_em IS NULL"
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
               AND solicitante_notificado_em IS NULL"
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

    private function userSnapshot(array $user): array
    {
        return [
            'usuario_id' => (int) ($user['id'] ?? $user['usuario_id'] ?? 0),
            'tipo' => 'coassinante',
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
