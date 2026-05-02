<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class UsuarioRepository
{
    public function all(): array
    {
        return Database::connection()
            ->query(
                'SELECT id, nome, cpf, email, telefone, orgao, unidade_setor, militar, graduacao, nome_guerra, matricula_funcional, perfil, ativo,
                        ultimo_acesso, criado_em
                 FROM usuarios
                 WHERE deleted_at IS NULL
                 ORDER BY ativo DESC, nome'
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function activeExcept(int $exceptUserId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, nome, cpf, email, telefone, orgao, unidade_setor, militar, graduacao, nome_guerra, matricula_funcional, perfil, ativo
             FROM usuarios
             WHERE ativo = 1
               AND deleted_at IS NULL
               AND id <> :except_user_id
             ORDER BY nome ASC'
        );
        $stmt->bindValue(':except_user_id', $exceptUserId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function activeByIds(array $ids): array
    {
        $normalizedIds = array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): int => (int) $id,
            $ids
        ), static fn (int $id): bool => $id > 0)));

        if ($normalizedIds === []) {
            return [];
        }

        $placeholders = [];
        foreach ($normalizedIds as $index => $id) {
            $placeholders[] = ':id_' . $index;
        }

        $stmt = Database::connection()->prepare(
            'SELECT id, nome, cpf, email, telefone, orgao, unidade_setor, militar, graduacao, nome_guerra, matricula_funcional, perfil, ativo
             FROM usuarios
             WHERE ativo = 1
               AND deleted_at IS NULL
               AND id IN (' . implode(', ', $placeholders) . ')
             ORDER BY nome ASC'
        );

        foreach ($normalizedIds as $index => $id) {
            $stmt->bindValue(':id_' . $index, $id, PDO::PARAM_INT);
        }

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, nome, cpf, email, telefone, orgao, unidade_setor, militar, graduacao, nome_guerra, matricula_funcional, senha_hash, perfil, ativo
             FROM usuarios
             WHERE id = :id AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($user) ? $user : null;
    }

    public function findActiveByEmail(string $email): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, nome, cpf, email, telefone, orgao, unidade_setor, militar, graduacao, nome_guerra, matricula_funcional, senha_hash, perfil, ativo
             FROM usuarios
             WHERE email = :email AND ativo = 1 AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->bindValue(':email', $email);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($user) ? $user : null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, nome, cpf, email, telefone, orgao, unidade_setor, militar, graduacao, nome_guerra, matricula_funcional, senha_hash, perfil, ativo
             FROM usuarios
             WHERE email = :email AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->bindValue(':email', $email);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($user) ? $user : null;
    }

    public function findByCpf(string $cpf): ?array
    {
        $cpfDigits = preg_replace('/\D+/', '', $cpf) ?? '';
        $stmt = Database::connection()->prepare(
            'SELECT id, nome, cpf, email, telefone, orgao, unidade_setor, militar, graduacao, nome_guerra, matricula_funcional, senha_hash, perfil, ativo
             FROM usuarios
             WHERE REPLACE(REPLACE(REPLACE(COALESCE(cpf, \'\'), \'.\', \'\'), \'-\', \'\'), \' \', \'\') = :cpf
                AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->bindValue(':cpf', $cpfDigits);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($user) ? $user : null;
    }

    public function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO usuarios
                (nome, cpf, email, telefone, orgao, unidade_setor, militar, graduacao, nome_guerra, matricula_funcional, senha_hash, perfil, ativo)
             VALUES
                (:nome, :cpf, :email, :telefone, :orgao, :unidade_setor, :militar, :graduacao, :nome_guerra, :matricula_funcional, :senha_hash, :perfil, :ativo)'
        );
        $stmt->bindValue(':nome', $data['nome']);
        $stmt->bindValue(':cpf', $data['cpf']);
        $stmt->bindValue(':email', $data['email']);
        $stmt->bindValue(':telefone', $data['telefone'] !== '' ? $data['telefone'] : null);
        $stmt->bindValue(':orgao', $data['orgao'] !== '' ? $data['orgao'] : null);
        $stmt->bindValue(':unidade_setor', $data['unidade_setor'] !== '' ? $data['unidade_setor'] : null);
        $stmt->bindValue(':militar', !empty($data['militar']) ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':graduacao', !empty($data['militar']) && $data['graduacao'] !== '' ? $data['graduacao'] : null);
        $stmt->bindValue(':nome_guerra', !empty($data['militar']) && $data['nome_guerra'] !== '' ? $data['nome_guerra'] : null);
        $stmt->bindValue(':matricula_funcional', !empty($data['militar']) && $data['matricula_funcional'] !== '' ? $data['matricula_funcional'] : null);
        $stmt->bindValue(':senha_hash', password_hash((string) $data['senha'], PASSWORD_DEFAULT));
        $stmt->bindValue(':perfil', $data['perfil']);
        $stmt->bindValue(':ativo', !empty($data['ativo']) ? 1 : 0, PDO::PARAM_INT);
        $stmt->execute();

        return (int) Database::connection()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE usuarios
             SET nome = :nome,
                 cpf = :cpf,
                 email = :email,
                 telefone = :telefone,
                 orgao = :orgao,
                 unidade_setor = :unidade_setor,
                 militar = :militar,
                 graduacao = :graduacao,
                 nome_guerra = :nome_guerra,
                 matricula_funcional = :matricula_funcional,
                 perfil = :perfil,
                 ativo = :ativo
             WHERE id = :id'
        );
        $stmt->bindValue(':nome', $data['nome']);
        $stmt->bindValue(':cpf', $data['cpf']);
        $stmt->bindValue(':email', $data['email']);
        $stmt->bindValue(':telefone', $data['telefone'] !== '' ? $data['telefone'] : null);
        $stmt->bindValue(':orgao', $data['orgao'] !== '' ? $data['orgao'] : null);
        $stmt->bindValue(':unidade_setor', $data['unidade_setor'] !== '' ? $data['unidade_setor'] : null);
        $stmt->bindValue(':militar', !empty($data['militar']) ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':graduacao', !empty($data['militar']) && $data['graduacao'] !== '' ? $data['graduacao'] : null);
        $stmt->bindValue(':nome_guerra', !empty($data['militar']) && $data['nome_guerra'] !== '' ? $data['nome_guerra'] : null);
        $stmt->bindValue(':matricula_funcional', !empty($data['militar']) && $data['matricula_funcional'] !== '' ? $data['matricula_funcional'] : null);
        $stmt->bindValue(':perfil', $data['perfil']);
        $stmt->bindValue(':ativo', !empty($data['ativo']) ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function updatePassword(int $id, string $password): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE usuarios SET senha_hash = :senha_hash WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->bindValue(':senha_hash', password_hash($password, PASSWORD_DEFAULT));
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function updatePasswordHash(int $id, string $hash): void
    {
        $info = password_get_info($hash);

        if (($info['algoName'] ?? 'unknown') === 'unknown') {
            throw new \InvalidArgumentException('Hash de senha invalido.');
        }

        $stmt = Database::connection()->prepare(
            'UPDATE usuarios SET senha_hash = :senha_hash WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->bindValue(':senha_hash', $hash);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
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
