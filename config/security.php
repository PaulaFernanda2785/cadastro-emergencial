<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'session_name' => Env::get('SESSION_NAME', 'cadastro_emergencial_session'),
    'session_same_site' => Env::get('SESSION_SAME_SITE', 'Lax'),
    'session_idle_timeout_seconds' => (int) Env::get('SESSION_IDLE_TIMEOUT_SECONDS', 1800),
    'idempotency_window_seconds' => (int) Env::get('IDEMPOTENCY_WINDOW_SECONDS', 5),
];
