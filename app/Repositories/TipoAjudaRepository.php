<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class TipoAjudaRepository
{
    public function all(): array
    {
        return Database::connection()
            ->query('SELECT id, nome, unidade_medida, ativo, criado_em FROM tipos_ajuda ORDER BY ativo DESC, nome')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function active(): array
    {
        return Database::connection()
            ->query('SELECT id, nome, unidade_medida FROM tipos_ajuda WHERE ativo = 1 ORDER BY nome')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, nome, unidade_medida, ativo FROM tipos_ajuda WHERE id = :id LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $tipo = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($tipo) ? $tipo : null;
    }

    public function create(string $nome, string $unidadeMedida): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO tipos_ajuda (nome, unidade_medida) VALUES (:nome, :unidade_medida)'
        );
        $stmt->bindValue(':nome', $nome);
        $stmt->bindValue(':unidade_medida', $unidadeMedida);
        $stmt->execute();

        return (int) Database::connection()->lastInsertId();
    }

    public function update(int $id, string $nome, string $unidadeMedida, bool $ativo): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE tipos_ajuda
             SET nome = :nome, unidade_medida = :unidade_medida, ativo = :ativo
             WHERE id = :id'
        );
        $stmt->bindValue(':nome', $nome);
        $stmt->bindValue(':unidade_medida', $unidadeMedida);
        $stmt->bindValue(':ativo', $ativo ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function countActive(): int
    {
        return (int) Database::connection()
            ->query('SELECT COUNT(*) FROM tipos_ajuda WHERE ativo = 1')
            ->fetchColumn();
    }
}
