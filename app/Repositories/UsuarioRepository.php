<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class UsuarioRepository
{
    public function findActiveByEmail(string $email): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, nome, cpf, email, telefone, orgao, unidade_setor, senha_hash, perfil, ativo
             FROM usuarios
             WHERE email = :email AND ativo = 1
             LIMIT 1'
        );
        $stmt->bindValue(':email', $email);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($user) ? $user : null;
    }

    public function touchLastAccess(int $userId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = :id'
        );
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }
}
