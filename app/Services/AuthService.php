<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UsuarioRepository;

final class AuthService
{
    public function __construct(
        private readonly UsuarioRepository $users = new UsuarioRepository()
    ) {
    }

    public function attempt(string $email, string $password): ?array
    {
        $user = $this->users->findActiveByEmail($email);

        if ($user === null || !password_verify($password, (string) $user['senha_hash'])) {
            return null;
        }

        if (password_needs_rehash((string) $user['senha_hash'], PASSWORD_DEFAULT)) {
            // Rehash sera tratado em rotina propria de manutencao de usuarios.
        }

        $this->users->touchLastAccess((int) $user['id']);
        unset($user['senha_hash']);

        return $user;
    }
}
