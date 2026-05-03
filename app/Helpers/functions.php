<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Session;
use App\Services\IdempotenciaService;

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = '/'): string
{
    $app = require BASE_PATH . '/config/app.php';
    $baseUrl = current_request_base_url($app) ?? rtrim((string) $app['url'], '/');

    return $baseUrl . '/' . ltrim($path, '/');
}

function public_url(string $path = '/'): string
{
    $app = require BASE_PATH . '/config/app.php';
    $baseUrl = rtrim((string) ($app['public_url'] ?? $app['url']), '/');

    return $baseUrl . '/' . ltrim($path, '/');
}

function current_request_base_url(array $app): ?string
{
    if (($app['env'] ?? null) !== 'local' || empty($_SERVER['HTTP_HOST'])) {
        return null;
    }

    $host = normalized_request_host((string) $_SERVER['HTTP_HOST']);
    if ($host === null) {
        return null;
    }

    $scheme = app_is_secure_request() ? 'https' : 'http';
    $scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')));

    if ($scriptDir === '/' || $scriptDir === '\\' || $scriptDir === '.') {
        $scriptDir = '';
    }

    return $scheme . '://' . $host . rtrim($scriptDir, '/');
}

function normalized_request_host(string $host): ?string
{
    $host = trim($host);

    if ($host === '' || strlen($host) > 255 || preg_match('/[\s\/\\\\@"\'<>]/', $host)) {
        return null;
    }

    if (!preg_match('/^(?:localhost|[a-z0-9.-]+|\[[0-9a-f:.]+\])(?::([0-9]{1,5}))?$/i', $host, $matches)) {
        return null;
    }

    if (isset($matches[1]) && ((int) $matches[1] < 1 || (int) $matches[1] > 65535)) {
        return null;
    }

    return strtolower($host);
}

function app_is_secure_request(): bool
{
    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $serverPort = (string) ($_SERVER['SERVER_PORT'] ?? '');

    return $https === 'on'
        || $https === '1'
        || $forwardedProto === 'https'
        || $serverPort === '443';
}

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(self), camera=(self), microphone=(), payment=(), usb=()');
    header('Feature-Policy: geolocation \'self\'; camera \'self\'; microphone \'none\'; payment \'none\'; usb \'none\'');
    header('X-Robots-Tag: noindex, nofollow');

    if (app_is_secure_request()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function asset(string $path): string
{
    return url('/assets/' . ltrim($path, '/'));
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf_token" value="' . h(Csrf::token()) . '">';
}

function idempotency_field(string $scope = 'default'): string
{
    $service = new IdempotenciaService();

    return '<input type="hidden" name="_idempotency_token" value="' . h($service->generate($scope)) . '">';
}

function current_user(): ?array
{
    $user = Session::get('user');

    return is_array($user) ? $user : null;
}

function is_authenticated(): bool
{
    return current_user() !== null;
}

function flash(string $key, mixed $default = null): mixed
{
    return Session::pullFlash($key, $default);
}

function residencia_imovel_options(): array
{
    return [
        'proprio' => 'Próprio',
        'alugado' => 'Alugado',
        'cedido' => 'Cedido',
    ];
}

function residencia_condicao_options(): array
{
    return [
        'perda_total' => 'Perda total',
        'perda_parcial' => 'Perda parcial',
        'nao_atingida' => 'Não atingida',
    ];
}

function residencia_imovel_label(mixed $value): string
{
    $options = residencia_imovel_options();
    $key = (string) $value;

    return $options[$key] ?? '-';
}

function residencia_condicao_label(mixed $value): string
{
    $options = residencia_condicao_options();
    $key = (string) $value;

    return $options[$key] ?? '-';
}

function familia_renda_label(mixed $value): string
{
    $options = [
        '0_3_salarios' => '0 a 3 salários',
        'acima_3_salarios' => 'Acima de 3 salários',
    ];
    $key = (string) $value;

    return $options[$key] ?? '-';
}

function familia_situacao_label(mixed $value): string
{
    $options = [
        'desabrigado' => 'Desabrigado',
        'desalojado' => 'Desalojado',
        'aluguel_social' => 'Aluguel social',
        'permanece_residencia' => 'Permanece na residência',
    ];
    $key = (string) $value;

    return $options[$key] ?? '-';
}

function familia_comprovante_codigo(array $familia): string
{
    $familiaId = (int) ($familia['id'] ?? 0);
    $residenciaId = (int) ($familia['residencia_id'] ?? 0);
    $hash = strtoupper(substr(hash('sha256', $familiaId . '|' . $residenciaId . '|cadastro-familiar'), 0, 10));

    return 'FAM-' . str_pad((string) $familiaId, 6, '0', STR_PAD_LEFT) . '-' . $hash;
}
