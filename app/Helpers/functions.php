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
    $baseUrl = current_request_base_url($app) ?? configured_app_url($app, 'url');

    return $baseUrl . '/' . ltrim($path, '/');
}

function public_url(string $path = '/'): string
{
    $app = require BASE_PATH . '/config/app.php';
    $baseUrl = configured_app_url($app, 'public_url');

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

function configured_app_url(array $app, string $key = 'url'): string
{
    $baseUrl = (string) ($app[$key] ?? $app['url'] ?? 'http://localhost');
    $baseUrl = rtrim($baseUrl, '/');

    if (should_force_https($app) && str_starts_with(strtolower($baseUrl), 'http://')) {
        return 'https://' . substr($baseUrl, 7);
    }

    return $baseUrl;
}

function should_force_https(array $app): bool
{
    return ($app['env'] ?? null) === 'production' || !empty($app['force_https']);
}

function enforce_https_request(array $app): void
{
    if (headers_sent() || app_is_secure_request() || !should_force_https($app)) {
        return;
    }

    $host = normalized_request_host((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === null) {
        return;
    }

    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    if ($requestUri === '' || preg_match('/[\r\n]/', $requestUri)) {
        $requestUri = '/';
    }

    header('Location: https://' . $host . $requestUri, true, 308);
    exit;
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
    $requestScheme = strtolower((string) ($_SERVER['REQUEST_SCHEME'] ?? ''));
    $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $forwardedSsl = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? ''));
    $forwardedPort = (string) ($_SERVER['HTTP_X_FORWARDED_PORT'] ?? '');
    $cloudflareVisitor = strtolower((string) ($_SERVER['HTTP_CF_VISITOR'] ?? ''));
    $serverPort = (string) ($_SERVER['SERVER_PORT'] ?? '');

    return $https === 'on'
        || $https === '1'
        || $requestScheme === 'https'
        || $forwardedProto === 'https'
        || $forwardedSsl === 'on'
        || $forwardedPort === '443'
        || str_contains($cloudflareVisitor, '"scheme":"https"')
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

function whatsapp_phone_digits(mixed $phone): string
{
    $digits = telefone_cadastro_digits($phone);

    if ($digits === '') {
        return '';
    }

    return '55' . $digits;
}

function telefone_cadastro_digits(mixed $phone): string
{
    $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

    if ($digits === '') {
        return '';
    }

    if (str_starts_with($digits, '55') && (strlen($digits) === 12 || strlen($digits) === 13)) {
        $digits = substr($digits, 2);
    } elseif (str_starts_with($digits, '0') && (strlen($digits) === 13 || strlen($digits) === 14)) {
        $digits = substr($digits, 3);
    } elseif (str_starts_with($digits, '0') && (strlen($digits) === 11 || strlen($digits) === 12)) {
        $digits = substr($digits, 1);
    }

    if (strlen($digits) === 10 || strlen($digits) === 11) {
        return $digits;
    }

    return '';
}

function telefone_cadastro_format(mixed $phone): string
{
    $original = trim((string) $phone);
    $digits = telefone_cadastro_digits($original);

    if ($digits === '') {
        return $original;
    }

    $ddd = substr($digits, 0, 2);
    $number = substr($digits, 2);

    if (strlen($number) === 9) {
        return '(' . $ddd . ') ' . substr($number, 0, 5) . '-' . substr($number, 5);
    }

    return '(' . $ddd . ') ' . substr($number, 0, 4) . '-' . substr($number, 4);
}

function whatsapp_direct_url(mixed $phone, string $text): string
{
    $digits = whatsapp_phone_digits($phone);

    if ($digits === '') {
        return '';
    }

    return 'https://api.whatsapp.com/send?phone=' . $digits . '&text=' . rawurlencode($text);
}

function whatsapp_app_url(mixed $phone, string $text): string
{
    $digits = whatsapp_phone_digits($phone);

    if ($digits === '') {
        return '';
    }

    return 'whatsapp://send?phone=' . $digits . '&text=' . rawurlencode($text);
}

function familia_whatsapp_destino(array $familia): array
{
    $responsavelPhone = whatsapp_phone_digits($familia['telefone'] ?? '');
    $responsavelName = trim((string) ($familia['responsavel_nome'] ?? ''));
    $representanteName = trim((string) ($familia['representante_nome'] ?? ''));
    $representanteCpf = trim((string) ($familia['representante_cpf'] ?? ''));
    $hasRepresentante = $representanteName !== '' || $representanteCpf !== '';
    $representantePhone = $hasRepresentante ? whatsapp_phone_digits($familia['representante_telefone'] ?? '') : '';
    $primaryPhone = $representantePhone !== '' ? $representantePhone : $responsavelPhone;
    $primaryType = $representantePhone !== '' ? 'representante' : 'responsavel';
    $primaryName = $representantePhone !== '' ? $representanteName : $responsavelName;
    $fallbackPhone = '';
    $fallbackName = '';

    if ($representantePhone !== '' && $responsavelPhone !== '' && $representantePhone !== $responsavelPhone) {
        $fallbackPhone = $responsavelPhone;
        $fallbackName = $responsavelName;
    }

    return [
        'telefone' => $primaryPhone,
        'nome' => $primaryName,
        'tipo' => $primaryType,
        'fallback_telefone' => $fallbackPhone,
        'fallback_nome' => $fallbackName,
    ];
}
