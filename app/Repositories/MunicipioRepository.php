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

    public function findByCode(string $codigoIbge): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, codigo_ibge, nome, uf, latitude, longitude FROM municipios WHERE codigo_ibge = :codigo_ibge LIMIT 1'
        );
        $stmt->bindValue(':codigo_ibge', $codigoIbge);
        $stmt->execute();

        $municipio = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($municipio) ? $municipio : null;
    }

    public function ensure(array $data): int
    {
        $existing = $this->findByCode((string) $data['codigo_ibge']);

        if ($existing !== null) {
            $stmt = Database::connection()->prepare(
                'UPDATE municipios
                 SET nome = :nome, uf = :uf, latitude = :latitude, longitude = :longitude
                 WHERE id = :id'
            );
            $stmt->bindValue(':nome', $data['nome']);
            $stmt->bindValue(':uf', $data['uf']);
            $stmt->bindValue(':latitude', $data['latitude'], $data['latitude'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':longitude', $data['longitude'], $data['longitude'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':id', (int) $existing['id'], PDO::PARAM_INT);
            $stmt->execute();

            return (int) $existing['id'];
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO municipios (codigo_ibge, nome, uf, latitude, longitude)
             VALUES (:codigo_ibge, :nome, :uf, :latitude, :longitude)'
        );
        $stmt->bindValue(':codigo_ibge', $data['codigo_ibge']);
        $stmt->bindValue(':nome', $data['nome']);
        $stmt->bindValue(':uf', $data['uf']);
        $stmt->bindValue(':latitude', $data['latitude'], $data['latitude'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':longitude', $data['longitude'], $data['longitude'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->execute();

        return (int) Database::connection()->lastInsertId();
    }

    public function count(): int
    {
        return (int) Database::connection()->query('SELECT COUNT(*) FROM municipios')->fetchColumn();
    }
}
