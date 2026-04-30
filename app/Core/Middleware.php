<?php

declare(strict_types=1);

namespace App\Core;

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
            Session::flash('warning', 'Acesse sua conta para continuar.');
            header('Location: ' . url('/login'));
            exit;
        }
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
}
