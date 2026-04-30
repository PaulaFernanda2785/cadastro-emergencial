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
    $baseUrl = rtrim((string) $app['url'], '/');

    return $baseUrl . '/' . ltrim($path, '/');
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
