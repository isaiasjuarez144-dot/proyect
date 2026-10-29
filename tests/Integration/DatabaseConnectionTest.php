<?php

declare(strict_types=1);

namespace UyuniTour\Tests\Integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class DatabaseConnectionTest extends TestCase
{
    public function testItConnectsToMySqlWithInnoDb(): void
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            getenv('DB_HOST'),
            getenv('DB_PORT') ?: '3306',
            getenv('DB_TEST_DATABASE') ?: 'uyuni_tour_test'
        );
        $connection = new PDO(
            $dsn,
            getenv('DB_USERNAME'),
            getenv('DB_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        self::assertSame(1, (int) $connection->query('SELECT 1')->fetchColumn());
        self::assertSame(
            'InnoDB',
            $connection->query('SELECT @@default_storage_engine')->fetchColumn()
        );
    }

    public function testUserSchemaEnforcesUniqueEmailsAndValidRoles(): void
    {
        $connection = new PDO(
            sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                getenv('DB_HOST'),
                getenv('DB_PORT') ?: '3306',
                getenv('DB_TEST_DATABASE') ?: 'uyuni_tour_test'
            ),
            getenv('DB_USERNAME'),
            getenv('DB_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $email = bin2hex(random_bytes(8)) . '@example.test';
        $insert = $connection->prepare(
            'INSERT INTO users (email, password_hash, role) VALUES (:email, :password_hash, :role)'
        );
        $parameters = [
            'email' => $email,
            'password_hash' => password_hash('schema-test-password', PASSWORD_DEFAULT),
            'role' => 'operator',
        ];
        $insert->execute($parameters);

        try {
            $duplicateRejected = false;
            try {
                $insert->execute($parameters);
            } catch (PDOException) {
                $duplicateRejected = true;
            }
            self::assertTrue($duplicateRejected);

            $invalidRoleRejected = false;
            try {
                $insert->execute([
                    ...$parameters,
                    'email' => bin2hex(random_bytes(8)) . '@example.test',
                    'role' => 'unknown',
                ]);
            } catch (PDOException) {
                $invalidRoleRejected = true;
            }
            self::assertTrue($invalidRoleRejected);
        } finally {
            $delete = $connection->prepare('DELETE FROM users WHERE email = :email');
            $delete->execute(['email' => $email]);
        }
    }
}
