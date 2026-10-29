<?php

declare(strict_types=1);

namespace UyuniTour\Infrastructure\Database;

use PDO;
use RuntimeException;

final class PdoConnectionFactory
{
    public static function createFromEnvironment(?string $databaseOverride = null): PDO
    {
        $host = getenv('DB_HOST') ?: 'db';
        $port = getenv('DB_PORT') ?: '3306';
        $database = $databaseOverride ?? (getenv('DB_DATABASE') ?: '');
        $username = getenv('DB_USERNAME') ?: '';
        $password = getenv('DB_PASSWORD') ?: '';
        if ($database === '' || $username === '' || $password === '') {
            throw new RuntimeException('Database configuration is incomplete.');
        }

        $connection = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database),
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        $connection->exec("SET time_zone = '+00:00'");

        return $connection;
    }
}
