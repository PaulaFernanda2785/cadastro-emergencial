<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UsuarioRepository;
use Throwable;

final class AuthService
{
    public function __construct(
        private readonly UsuarioRepository $users = new UsuarioRepository()
    ) {
    }

    public function attempt(string $email, string $password): ?array
    {
        $user = $this->users->findActiveByEmail($email);

        if ($user === null) {
            return null;
        }

        $storedHash = (string) $user['senha_hash'];
        $verifyHash = $this->normalizeLegacyBcryptHash($storedHash);

        if (!$this->isSupportedPasswordHash($verifyHash)) {
            error_log('Hash de senha invalido para usuario id ' . (int) $user['id']);
            return null;
        }

        if (!password_verify($password, $verifyHash)) {
            return null;
        }

        if ($verifyHash !== $storedHash || password_needs_rehash($verifyHash, PASSWORD_DEFAULT)) {
            try {
                $this->users->updatePasswordHash((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
            } catch (Throwable) {
                error_log('Falha ao atualizar hash de senha para usuario id ' . (int) $user['id']);
            }
        }

        $this->users->touchLastAccess((int) $user['id']);
        unset($user['senha_hash']);

        return $user;
    }

    private function normalizeLegacyBcryptHash(string $hash): string
    {
        if (str_starts_with($hash, '$2a$') || str_starts_with($hash, '$2x$')) {
            return '$2y$' . substr($hash, 4);
        }

        return $hash;
    }

    private function isSupportedPasswordHash(string $hash): bool
    {
        return (password_get_info($hash)['algoName'] ?? 'unknown') !== 'unknown';
    }
}
