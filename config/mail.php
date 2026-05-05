<?php

declare(strict_types=1);

use App\Core\Env;

$enabled = strtolower((string) Env::get('SMTP_ENABLED', Env::get('MAIL_ENABLED', 'true')));

return [
    'enabled' => !in_array($enabled, ['0', 'false', 'off', 'no'], true),
    'from_email' => (string) Env::get('SMTP_FROM_EMAIL', Env::get('MAIL_FROM_EMAIL', 'no-reply@localhost')),
    'from_name' => (string) Env::get('SMTP_FROM_NAME', Env::get('MAIL_FROM_NAME', Env::get('APP_NAME', 'Cadastro Emergencial'))),
    'reply_to' => (string) Env::get('SMTP_REPLY_TO', Env::get('MAIL_REPLY_TO', '')),
    'smtp' => [
        'host' => (string) Env::get('SMTP_HOST', ''),
        'port' => (int) Env::get('SMTP_PORT', 465),
        'user' => (string) Env::get('SMTP_USER', ''),
        'pass' => (string) Env::get('SMTP_PASS', ''),
        'secure' => strtolower((string) Env::get('SMTP_SECURE', 'ssl')),
    ],
];
