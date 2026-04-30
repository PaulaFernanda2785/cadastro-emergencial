<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class MunicipioRepository
{
    public function all(?string $uf = null): array
    {
        if ($uf !== null && $uf !== '') {
            $stmt = Database::connection()->prepare(
                'SELECT id, codigo_ibge, nome, uf, latitude, longitude
                 FROM municipios
                 WHERE uf = :uf
                 ORDER BY nome'
            );
            $stmt->bindValue(':uf', strtoupper($uf));
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return Database::connection()
            ->query('SELECT id, codigo_ibge, nome, uf, latitude, longitude FROM municipios ORDER BY uf, nome')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, codigo_ibge, nome, uf, latitude, longitude FROM municipios WHERE id = :id LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $municipio = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($municipio) ? $municipio : null;
    }

    public function count(): int
    {
        return (int) Database::connection()->query('SELECT COUNT(*) FROM municipios')->fetchColumn();
    }
}
