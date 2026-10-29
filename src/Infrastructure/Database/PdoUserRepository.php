<?php

declare(strict_types=1);

namespace UyuniTour\Infrastructure\Database;

use PDO;
use Throwable;
use UyuniTour\Domain\Identity\Role;
use UyuniTour\Domain\Identity\User;
use UyuniTour\Domain\Identity\UserRepository;
use UyuniTour\Domain\Shared\NotFoundException;

final class PdoUserRepository implements UserRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function findByEmail(string $email): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, email, password_hash, role, is_active, created_at, updated_at '
            . 'FROM users WHERE email = :email LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $record = $statement->fetch();

        return $record === false ? null : $record;
    }

    public function findById(int $id): ?User
    {
        $statement = $this->connection->prepare(
            'SELECT id, email, role, is_active, created_at, updated_at '
            . 'FROM users WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $record = $statement->fetch();

        return $record === false ? null : self::toUser($record);
    }

    public function list(): array
    {
        $records = $this->connection
            ->query('SELECT id, email, role, is_active, created_at, updated_at FROM users ORDER BY id')
            ->fetchAll();

        return array_map(self::toUser(...), $records);
    }

    public function countAdministrators(bool $activeOnly): int
    {
        $sql = "SELECT COUNT(*) FROM users WHERE role = 'administrator'";
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }

        return (int) $this->connection->query($sql)->fetchColumn();
    }

    public function create(string $email, string $passwordHash, Role $role): User
    {
        $statement = $this->connection->prepare(
            'INSERT INTO users (email, password_hash, role, is_active) '
            . 'VALUES (:email, :password_hash, :role, 1)'
        );
        $statement->execute([
            'email' => $email,
            'password_hash' => $passwordHash,
            'role' => $role->value,
        ]);

        return $this->findById((int) $this->connection->lastInsertId())
            ?? throw new \RuntimeException('Created user could not be reloaded.');
    }

    public function update(int $id, ?Role $role, ?bool $isActive): User
    {
        $fields = [];
        $parameters = ['id' => $id];
        if ($role !== null) {
            $fields[] = 'role = :role';
            $parameters['role'] = $role->value;
        }
        if ($isActive !== null) {
            $fields[] = 'is_active = :is_active';
            $parameters['is_active'] = (int) $isActive;
        }

        $statement = $this->connection->prepare(
            'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id'
        );
        $statement->execute($parameters);

        return $this->findById($id) ?? throw new NotFoundException('Usuario no encontrado.');
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $statement = $this->connection->prepare(
            'UPDATE users SET password_hash = :password_hash WHERE id = :id'
        );
        $statement->execute(['password_hash' => $passwordHash, 'id' => $id]);
        if ($statement->rowCount() === 0 && $this->findById($id) === null) {
            throw new NotFoundException('Usuario no encontrado.');
        }
    }

    public function hasAnyAdministrator(): bool
    {
        return $this->countAdministrators(false) > 0;
    }

    public function withAdministratorLock(callable $operation): mixed
    {
        $lock = $this->connection->query("SELECT GET_LOCK('uyuni_active_administrator', 10)")
            ->fetchColumn();
        if ((int) $lock !== 1) {
            throw new \RuntimeException('Could not acquire the administrator safety lock.');
        }

        $this->connection->beginTransaction();
        try {
            $result = $operation();
            $this->connection->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        } finally {
            $this->connection->query("SELECT RELEASE_LOCK('uyuni_active_administrator')");
        }
    }

    private static function toUser(array $record): User
    {
        return new User(
            (int) $record['id'],
            (string) $record['email'],
            Role::from((string) $record['role']),
            (bool) $record['is_active'],
            (string) $record['created_at'],
            (string) $record['updated_at']
        );
    }
}
