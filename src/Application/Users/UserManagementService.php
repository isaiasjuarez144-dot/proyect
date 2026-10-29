<?php

declare(strict_types=1);

namespace UyuniTour\Application\Users;

use UyuniTour\Application\Auth\AuthService;
use UyuniTour\Domain\Identity\Role;
use UyuniTour\Domain\Identity\User;
use UyuniTour\Domain\Identity\UserRepository;
use UyuniTour\Domain\Shared\AuthorizationException;
use UyuniTour\Domain\Shared\NotFoundException;
use UyuniTour\Domain\Shared\ValidationException;
use UyuniTour\Infrastructure\Security\PasswordHasher;

final class UserManagementService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $passwords
    ) {
    }

    public function list(User $actor): array
    {
        $this->assertAdministrator($actor);

        return $this->users->list();
    }

    public function create(User $actor, string $email, string $password, string $role): User
    {
        $this->assertAdministrator($actor);
        $parsedRole = Role::tryFrom($role);
        if ($parsedRole === null) {
            throw new ValidationException('El rol no es válido.');
        }

        return $this->users->create(
            AuthService::normalizeEmail($email),
            $this->passwords->hash($password),
            $parsedRole
        );
    }

    public function update(User $actor, int $id, ?string $role, ?bool $active): User
    {
        $this->assertAdministrator($actor);
        if ($role === null && $active === null) {
            throw new ValidationException('Indica al menos un cambio.');
        }

        $parsedRole = $role === null ? null : Role::tryFrom($role);
        if ($role !== null && $parsedRole === null) {
            throw new ValidationException('El rol no es válido.');
        }

        return $this->users->withAdministratorLock(function () use (
            $id,
            $parsedRole,
            $active
        ): User {
            $target = $this->users->findById($id);
            if ($target === null) {
                throw new NotFoundException('Usuario no encontrado.');
            }

            $removesActiveAdministrator = $target->role === Role::Administrator
                && $target->isActive
                && ($parsedRole !== null && $parsedRole !== Role::Administrator
                    || $active === false);
            if (
                $removesActiveAdministrator
                && $this->users->countAdministrators(true) <= 1
            ) {
                throw new ValidationException('Debe permanecer al menos un administrador activo.');
            }

            return $this->users->update($id, $parsedRole, $active);
        });
    }

    public function resetPassword(User $actor, int $id, string $password): void
    {
        $this->assertAdministrator($actor);
        if ($this->users->findById($id) === null) {
            throw new NotFoundException('Usuario no encontrado.');
        }

        $this->users->updatePassword($id, $this->passwords->hash($password));
    }

    public function initializeFirstAdministrator(string $email, string $password): bool
    {
        return $this->users->withAdministratorLock(function () use ($email, $password): bool {
            if ($this->users->hasAnyAdministrator()) {
                return false;
            }

            $this->users->create(
                AuthService::normalizeEmail($email),
                $this->passwords->hash($password),
                Role::Administrator
            );

            return true;
        });
    }

    private function assertAdministrator(User $actor): void
    {
        if ($actor->role !== Role::Administrator) {
            throw new AuthorizationException('Permiso insuficiente.');
        }
    }
}
