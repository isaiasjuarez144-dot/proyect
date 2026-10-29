<?php

declare(strict_types=1);

namespace UyuniTour\Infrastructure\Database;

use PDO;

final class MigrationRunner
{
    public function __construct(
        private readonly PDO $connection,
        private readonly string $directory
    ) {
    }

    public function run(): array
    {
        $this->connection->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations ('
            . 'migration VARCHAR(255) NOT NULL PRIMARY KEY, '
            . 'applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $applied = $this->connection->query('SELECT migration FROM schema_migrations')
            ->fetchAll(PDO::FETCH_COLUMN);
        $completed = [];

        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*.sql') ?: [] as $path) {
            $name = basename($path);
            if (in_array($name, $applied, true)) {
                continue;
            }

            $this->connection->exec((string) file_get_contents($path));
            $statement = $this->connection->prepare(
                'INSERT INTO schema_migrations (migration) VALUES (:migration)'
            );
            $statement->execute(['migration' => $name]);
            $completed[] = $name;
        }

        return $completed;
    }
}
