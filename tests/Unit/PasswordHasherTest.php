<?php

declare(strict_types=1);

namespace UyuniTour\Tests\Unit;

use PHPUnit\Framework\TestCase;
use UyuniTour\Domain\Shared\ValidationException;
use UyuniTour\Infrastructure\Security\PasswordHasher;

final class PasswordHasherTest extends TestCase
{
    public function testItHashesAndVerifiesWithoutStoringThePassword(): void
    {
        $password = 'local-test-password-123';
        $hasher = new PasswordHasher();
        $hash = $hasher->hash($password);

        self::assertNotSame($password, $hash);
        $expectedAlgorithm = defined('PASSWORD_ARGON2ID') ? 'argon2id' : 'bcrypt';
        self::assertSame($expectedAlgorithm, password_get_info($hash)['algoName']);
        self::assertTrue($hasher->verify($password, $hash));
        self::assertFalse($hasher->verify('another-password', $hash));
    }

    public function testItRejectsEmptyPasswords(): void
    {
        $this->expectException(ValidationException::class);
        (new PasswordHasher())->hash('');
    }

    public function testBcryptRejectsPasswordsLongerThanItsByteLimit(): void
    {
        $hasher = new PasswordHasher(false);
        $this->expectException(ValidationException::class);
        $hasher->hash(str_repeat('a', 73));
    }
}
