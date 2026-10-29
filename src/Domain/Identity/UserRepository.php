<?php

declare(strict_types=1);

namespace UyuniTour\Domain\Identity;

interface UserRepository
{
    public function findByEmail(string $email): ?array;

    public function findById(int $id): ?User;

    public function list(): array;

    public function countAdministrators(bool $activeOnly): int;

    public function create(string $email, string $passwordHash, Role $role): User;

    public function update(int $id, ?Role $role, ?bool $isActive): User;

    public function updatePassword(int $id, string $passwordHash): void;

    public function hasAnyAdministrator(): bool;

    public function withAdministratorLock(callable $operation): mixed;
}
