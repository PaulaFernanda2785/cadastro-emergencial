<?php

declare(strict_types=1);

namespace App\Core;

use App\Services\AuditLogService;

final class Middleware
{
    public static function handle(array $middleware): void
    {
        foreach ($middleware as $rule) {
            if ($rule === 'auth') {
                self::auth();
                continue;
            }

            if ($rule === 'guest') {
                self::guest();
                continue;
            }

            if (str_starts_with($rule, 'role:')) {
                $roles = explode(',', substr($rule, 5));
                self::role($roles);
            }
        }
    }

    private static function auth(): void
    {
        if (!is_authenticated()) {
            $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

            if ($method === 'GET') {
                Session::put('intended_url', self::intendedPath());
            }

            Session::flash('warning', 'Acesse sua conta para continuar.');
            header('Location: ' . url('/login?intended=1'));
            exit;
        }

        self::enforceIdleTimeout();
    }

    private static function enforceIdleTimeout(): void
    {
        $config = require BASE_PATH . '/config/security.php';
        $timeout = max(60, (int) ($config['session_idle_timeout_seconds'] ?? 1800));
        $now = time();
        $lastActivity = (int) Session::get('last_activity_at', $now);

        if (($now - $lastActivity) > $timeout) {
            $user = current_user();
            $userId = is_array($user) && is_numeric($user['id'] ?? null) ? (int) $user['id'] : null;

            if ($userId !== null) {
                (new AuditLogService())->record('logout_inatividade', 'usuarios', $userId, 'Sessao encerrada por inatividade.', $userId);
            }

            Session::destroy();
            Session::start();
            Session::flash('warning', 'Sessao encerrada por inatividade apos 30 minutos.');
            header('Location: ' . url('/login?timeout=1'));
            exit;
        }

        Session::put('last_activity_at', $now);
    }

    private static function guest(): void
    {
        if (is_authenticated()) {
            header('Location: ' . url('/dashboard'));
            exit;
        }
    }

    private static function role(array $allowedRoles): void
    {
        $user = current_user();
        $profile = $user['perfil'] ?? null;

        if (!is_string($profile) || !in_array($profile, $allowedRoles, true)) {
            http_response_code(403);
            View::render('errors.403', ['title' => 'Acesso negado']);
            exit;
        }
    }

    private static function intendedPath(): string
    {
        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/dashboard');
        $path = parse_url($requestUri, PHP_URL_PATH) ?: '/dashboard';
        $query = parse_url($requestUri, PHP_URL_QUERY);
        $scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')));

        if ($scriptDir !== '/' && $scriptDir !== '\\' && $scriptDir !== '.' && str_starts_with($path, $scriptDir)) {
            $path = substr($path, strlen($scriptDir)) ?: '/';
        }

        $path = '/' . ltrim($path, '/');

        return $query ? $path . '?' . $query : $path;
    }
}
