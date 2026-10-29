<?php

declare(strict_types=1);

namespace UyuniTour\Application\Auth;

use UyuniTour\Domain\Auth\LoginAttemptRepository;
use UyuniTour\Domain\Auth\TokenIssuer;
use UyuniTour\Domain\Identity\User;
use UyuniTour\Domain\Identity\UserRepository;
use UyuniTour\Domain\Shared\AuthenticationException;
use UyuniTour\Domain\Shared\ValidationException;
use UyuniTour\Infrastructure\Security\PasswordHasher;

final class AuthService
{
    private const MAX_ATTEMPTS = 5;
    private const DUMMY_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    public function __construct(
        private readonly UserRepository $users,
        private readonly LoginAttemptRepository $attempts,
        private readonly PasswordHasher $passwords,
        private readonly TokenIssuer $tokens
    ) {
    }

    public function login(string $email, string $password, string $ipAddress): array
    {
        $email = self::normalizeEmail($email);
        if (strlen($password) > 4096) {
            throw new ValidationException('La contraseña no es válida.');
        }
        $emailLimitKey = hash('sha256', "email\0" . $email);
        $ipLimitKey = hash('sha256', "ip\0" . $ipAddress);
        if (
            $this->attempts->isLocked($emailLimitKey)
            || $this->attempts->isLocked($ipLimitKey)
        ) {
            throw new AuthenticationException('Credenciales incorrectas.');
        }

        $record = $this->users->findByEmail($email);
        $validPassword = $this->passwords->verify(
            $password,
            $record['password_hash'] ?? self::DUMMY_HASH
        );

        if (
            $record === null
            || !$validPassword
            || !(bool) $record['is_active']
        ) {
            $this->attempts->recordFailure($emailLimitKey);
            $this->attempts->recordFailure($ipLimitKey);
            throw new AuthenticationException('Credenciales incorrectas.');
        }

        $this->attempts->clear($emailLimitKey);
        $this->attempts->clear($ipLimitKey);
        $user = $this->users->findById((int) $record['id']);
        if ($user === null || !$user->isActive) {
            throw new AuthenticationException('Credenciales incorrectas.');
        }

        return ['token' => $this->tokens->issue($user), 'user' => $user];
    }

    public function authenticatedUser(string $token): User
    {
        $user = $this->users->findById($this->tokens->subject($token));
        if ($user === null || !$user->isActive) {
            throw new AuthenticationException('No autenticado.');
        }

        return $user;
    }

    public static function normalizeEmail(string $email): string
    {
        $normalized = strtolower(trim($email));
        if (
            strlen($normalized) > 254
            || filter_var($normalized, FILTER_VALIDATE_EMAIL) === false
        ) {
            throw new ValidationException('El correo electrónico no es válido.');
        }

        return $normalized;
    }
}
