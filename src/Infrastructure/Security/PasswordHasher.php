<?php

declare(strict_types=1);

namespace UyuniTour\Infrastructure\Security;

use UyuniTour\Domain\Shared\ValidationException;

final class PasswordHasher
{
    public function __construct(private readonly bool $preferArgon2id = true)
    {
    }

    public function hash(string $password): string
    {
        if ($password === '' || strlen($password) > 4096) {
            throw new ValidationException('La contraseña no es válida.');
        }

        $algorithm = $this->preferArgon2id && defined('PASSWORD_ARGON2ID')
            ? PASSWORD_ARGON2ID
            : PASSWORD_BCRYPT;
        if ($algorithm === PASSWORD_BCRYPT && strlen($password) > 72) {
            throw new ValidationException('La contraseña excede el límite seguro de BCrypt.');
        }

        $hash = password_hash($password, $algorithm);
        if ($hash === false) {
            throw new \RuntimeException('No se pudo proteger la contraseña.');
        }

        return $hash;
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}
