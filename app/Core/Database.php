<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $config = require BASE_PATH . '/config/database.php';

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        try {
            self::$connection = new PDO($dsn, $config['username'], $config['password'], $config['options']);
            self::configureSessionTimezone(self::$connection, (string) ($config['timezone'] ?? '-03:00'));
        } catch (PDOException $exception) {
            throw new RuntimeException('Falha ao conectar ao banco de dados.', 0, $exception);
        }

        return self::$connection;
    }

    private static function configureSessionTimezone(PDO $connection, string $timezone): void
    {
        if (!preg_match('/^[+-](?:0\d|1[0-4]):[0-5]\d$/', $timezone)) {
            $timezone = '-03:00';
        }

        $connection->exec("SET time_zone = '{$timezone}'");
    }
}
