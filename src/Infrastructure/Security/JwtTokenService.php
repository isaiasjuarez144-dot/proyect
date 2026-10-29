<?php

declare(strict_types=1);

namespace UyuniTour\Infrastructure\Security;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;
use UyuniTour\Domain\Auth\TokenIssuer;
use UyuniTour\Domain\Identity\User;
use UyuniTour\Domain\Shared\AuthenticationException;

final class JwtTokenService implements TokenIssuer
{
    public function __construct(
        private readonly string $secret,
        private readonly int $ttlSeconds
    ) {
        if (strlen($secret) < 64) {
            throw new \InvalidArgumentException('JWT_SECRET must contain at least 64 characters.');
        }
        if ($ttlSeconds < 60 || $ttlSeconds > 3600) {
            throw new \InvalidArgumentException('JWT_TTL_SECONDS must be between 60 and 3600.');
        }
    }

    public function issue(User $user): string
    {
        $now = time();

        return JWT::encode([
            'sub' => (string) $user->id,
            'iat' => $now,
            'exp' => $now + $this->ttlSeconds,
            'jti' => bin2hex(random_bytes(16)),
        ], $this->secret, 'HS256');
    }

    public function subject(string $token): int
    {
        try {
            $claims = JWT::decode($token, new Key($this->secret, 'HS256'));
            if (!isset($claims->sub) || !ctype_digit((string) $claims->sub)) {
                throw new AuthenticationException('No autenticado.');
            }

            return (int) $claims->sub;
        } catch (Throwable $exception) {
            throw new AuthenticationException('No autenticado.', 0, $exception);
        }
    }
}
