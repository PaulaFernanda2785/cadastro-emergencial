<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\LogRepository;
use Throwable;

final class AuditLogService
{
    public function __construct(
        private readonly LogRepository $logs = new LogRepository()
    ) {
    }

    public function record(string $action, string $entity, ?int $entityId = null, ?string $description = null, ?int $userId = null): void
    {
        try {
            $this->logs->create([
                'usuario_id' => $userId ?? (current_user()['id'] ?? null),
                'acao' => $action,
                'entidade' => $entity,
                'entidade_id' => $entityId,
                'descricao' => $description,
                'ip_origem' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);
        } catch (Throwable) {
            error_log("Falha ao registrar log de auditoria: {$action} {$entity}");
        }
    }
}
