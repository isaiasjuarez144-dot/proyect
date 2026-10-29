<?php

declare(strict_types=1);

namespace UyuniTour\Tests\Integration;

use Firebase\JWT\JWT;
use PDO;
use PHPUnit\Framework\TestCase;
use UyuniTour\Application\Users\UserManagementService;
use UyuniTour\Domain\Identity\Role;
use UyuniTour\Domain\Shared\AuthenticationException;
use UyuniTour\Domain\Shared\ValidationException;
use PDOException;
use UyuniTour\Infrastructure\Bootstrap\ApplicationFactory;
use UyuniTour\Infrastructure\Database\PdoConnectionFactory;
use UyuniTour\Infrastructure\Database\PdoLoginAttemptRepository;
use UyuniTour\Infrastructure\Database\PdoUserRepository;
use UyuniTour\Infrastructure\Http\Request;
use UyuniTour\Infrastructure\Http\Response;
use UyuniTour\Infrastructure\Security\PasswordHasher;

final class AuthenticationApiTest extends TestCase
{
    private PDO $connection;
    private PdoUserRepository $users;
    private PasswordHasher $passwords;
    private array $createdUserIds = [];
    private array $attemptHashes = [];

    protected function setUp(): void
    {
        $this->connection = PdoConnectionFactory::createFromEnvironment(
            getenv('DB_TEST_DATABASE') ?: 'uyuni_tour_test'
        );
        $this->users = new PdoUserRepository($this->connection);
        $this->passwords = new PasswordHasher();
    }

    protected function tearDown(): void
    {
        $attempts = new PdoLoginAttemptRepository($this->connection);
        foreach ($this->attemptHashes as $hash) {
            $attempts->clear($hash);
        }
        foreach ($this->createdUserIds as $id) {
            $audit = $this->connection->prepare('DELETE FROM audit_events WHERE actor_id = :id');
            $audit->execute(['id' => $id]);
            $statement = $this->connection->prepare('DELETE FROM users WHERE id = :id');
            $statement->execute(['id' => $id]);
        }
    }

    public function testLoginAndGenericInvalidCredentialsResponse(): void
    {
        $password = 'Integration-password-1';
        $user = $this->createUser(Role::Operator, $password);
        $good = $this->request('POST', '/api/v1/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ]);
        $bad = $this->request('POST', '/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'incorrect-password',
        ]);

        self::assertSame(200, $good->status);
        self::assertNotEmpty($good->body['data']['token']);
        self::assertSame($user->email, $good->body['data']['user']['email']);
        self::assertSame(401, $bad->status);
        self::assertSame('No autenticado o credenciales incorrectas.', $bad->body['error']['message']);
    }

    public function testExpiredAndInvalidTokensAreRejected(): void
    {
        $user = $this->createUser(Role::Administrator, 'Integration-password-2');
        $secret = (string) getenv('JWT_SECRET');
        $expired = JWT::encode([
            'sub' => (string) $user->id,
            'iat' => time() - 120,
            'exp' => time() - 60,
        ], $secret, 'HS256');

        self::assertSame(401, $this->request('GET', '/api/v1/auth/me')->status);
        self::assertSame(401, $this->request('GET', '/api/v1/auth/me', null, $expired)->status);
        self::assertSame(
            401,
            $this->request('GET', '/api/v1/auth/me', null, 'invalid.token.value')->status
        );
        $wrongAlgorithm = JWT::encode([
            'sub' => (string) $user->id,
            'iat' => time(),
            'exp' => time() + 60,
        ], $secret, 'HS384');
        self::assertSame(
            401,
            $this->request('GET', '/api/v1/auth/me', null, $wrongAlgorithm)->status
        );

        $this->expectException(AuthenticationException::class);
        ApplicationFactory::tokenIssuer()->subject($expired);
    }

    public function testInactiveAccountsAndUpdatedRolesTakeEffectImmediately(): void
    {
        $user = $this->createUser(Role::Operator, 'Integration-password-3');
        $login = $this->request('POST', '/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Integration-password-3',
        ]);
        $token = $login->body['data']['token'];

        $this->users->update($user->id, Role::Administrator, null);
        self::assertSame(200, $this->request('GET', '/api/v1/users', null, $token)->status);

        $this->users->update($user->id, null, false);
        self::assertSame(401, $this->request('GET', '/api/v1/auth/me', null, $token)->status);
        self::assertSame(401, $this->request('POST', '/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Integration-password-3',
        ])->status);
    }

    public function testProtectedRoutesDistinguishMissingAuthenticationAndPermission(): void
    {
        $operator = $this->createUser(Role::Operator, 'Integration-password-4');
        self::assertSame(401, $this->request('GET', '/api/v1/users')->status);
        self::assertSame(401, $this->request('POST', '/api/v1/users')->status);

        $login = $this->request('POST', '/api/v1/auth/login', [
            'email' => $operator->email,
            'password' => 'Integration-password-4',
        ]);
        $token = $login->body['data']['token'];
        self::assertSame(403, $this->request('GET', '/api/v1/users', null, $token)->status);
        self::assertSame(403, $this->request('POST', '/api/v1/users', [
            'email' => 'new@example.test',
            'password' => 'new-password-1',
            'role' => Role::Auditor->value,
        ], $token)->status);
    }

    public function testAdministratorCanManageUsersButCannotRemoveTheLastActiveAdministrator(): void
    {
        $admin = $this->createUser(Role::Administrator, 'Integration-password-5');
        $service = new UserManagementService($this->users, $this->passwords);

        try {
            $service->update($admin, $admin->id, null, false);
            self::fail('The last active administrator must be protected.');
        } catch (ValidationException $exception) {
            self::assertSame(
                'Debe permanecer al menos un administrador activo.',
                $exception->getMessage()
            );
        }

        $operator = $service->create(
            $admin,
            'Operator@Example.test',
            'Integration-password-6',
            Role::Operator->value
        );
        $this->createdUserIds[] = $operator->id;
        self::assertSame('operator@example.test', $operator->email);
        $updated = $service->update($admin, $operator->id, Role::Auditor->value, false);
        self::assertSame(Role::Auditor, $updated->role);
        self::assertFalse($updated->isActive);

        $service->resetPassword($admin, $operator->id, 'Reset-password-7');
        $record = $this->users->findByEmail($operator->email);
        self::assertIsArray($record);
        self::assertTrue($this->passwords->verify('Reset-password-7', $record['password_hash']));
    }

    public function testAdministratorUserManagementEndpoints(): void
    {
        $admin = $this->createUser(Role::Administrator, 'Integration-password-10');
        $login = $this->request('POST', '/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'Integration-password-10',
        ]);
        $token = $login->body['data']['token'];

        $listing = $this->request('GET', '/api/v1/users', null, $token);
        self::assertSame(200, $listing->status);
        self::assertArrayNotHasKey('password_hash', $listing->body['data'][0]);

        $created = $this->request('POST', '/api/v1/users', [
            'email' => 'new-operator@example.test',
            'password' => 'Integration-password-11',
            'role' => Role::Operator->value,
        ], $token);
        self::assertSame(201, $created->status);
        self::assertArrayNotHasKey('password_hash', $created->body['data']);
        $id = $created->body['data']['id'];
        $this->createdUserIds[] = $id;
        self::assertSame(409, $this->request('POST', '/api/v1/users', [
            'email' => 'new-operator@example.test',
            'password' => 'Another-password-12',
            'role' => Role::Operator->value,
        ], $token)->status);
        self::assertSame(422, $this->request('POST', '/api/v1/users', [
            'email' => 'invalid-role@example.test',
            'password' => 'Another-password-13',
            'role' => 'unknown',
        ], $token)->status);

        $updated = $this->request('PATCH', '/api/v1/users/' . $id, [
            'role' => Role::Auditor->value,
            'active' => false,
        ], $token);
        self::assertSame(200, $updated->status);
        self::assertSame(Role::Auditor->value, $updated->body['data']['role']);
        self::assertFalse($updated->body['data']['active']);

        self::assertSame(204, $this->request('POST', '/api/v1/users/' . $id . '/password', [
            'password' => 'Integration-password-12',
        ], $token)->status);
        self::assertSame(422, $this->request('PATCH', '/api/v1/users/' . $admin->id, [
            'active' => false,
        ], $token)->status);
    }

    public function testFirstAdministratorInitializationIsIdempotent(): void
    {
        $email = bin2hex(random_bytes(8)) . '@example.test';
        $service = new UserManagementService($this->users, $this->passwords);
        self::assertTrue($service->initializeFirstAdministrator(
            $email,
            'Integration-password-13'
        ));
        $record = $this->users->findByEmail($email);
        self::assertIsArray($record);
        $this->createdUserIds[] = (int) $record['id'];
        self::assertFalse($service->initializeFirstAdministrator(
            $email,
            'Different-password-14'
        ));
        self::assertTrue(
            $this->passwords->verify(
                'Integration-password-13',
                $this->users->findByEmail($email)['password_hash']
            )
        );
    }

    public function testLoginAttemptLimitPersistsAcrossRequests(): void
    {
        $password = 'Integration-password-8';
        $user = $this->createUser(Role::Operator, $password);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            self::assertSame(401, $this->request('POST', '/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->status);
        }

        self::assertSame(401, $this->request('POST', '/api/v1/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ])->status);
    }

    public function testLoginAttemptLimitAlsoAppliesAcrossEmailsFromOneIp(): void
    {
        $first = $this->createUser(Role::Operator, 'Integration-password-15');
        $second = $this->createUser(Role::Operator, 'Integration-password-16');
        for ($attempt = 0; $attempt < 4; $attempt++) {
            self::assertSame(401, $this->request('POST', '/api/v1/auth/login', [
                'email' => $first->email,
                'password' => 'wrong-password',
            ])->status);
        }
        self::assertSame(401, $this->request('POST', '/api/v1/auth/login', [
            'email' => $second->email,
            'password' => 'wrong-password',
        ])->status);
        self::assertSame(401, $this->request('POST', '/api/v1/auth/login', [
            'email' => $second->email,
            'password' => 'Integration-password-16',
        ])->status);
    }

    public function testLogoutLeavesTheIssuedTokenValidUntilItExpires(): void
    {
        $user = $this->createUser(Role::Administrator, 'Integration-password-9');
        $login = $this->request('POST', '/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Integration-password-9',
        ]);
        $token = $login->body['data']['token'];

        self::assertSame(204, $this->request('POST', '/api/v1/auth/logout', null, $token)->status);
        self::assertSame(200, $this->request('GET', '/api/v1/auth/me', null, $token)->status);
    }

    private function createUser(Role $role, string $password): \UyuniTour\Domain\Identity\User
    {
        $email = bin2hex(random_bytes(8)) . '@example.test';
        $user = $this->users->create($email, $this->passwords->hash($password), $role);
        $this->createdUserIds[] = $user->id;
        $this->attemptHashes[] = hash('sha256', "email\0" . $email);
        $this->attemptHashes[] = hash('sha256', "ip\0" . '127.0.0.1');

        return $user;
    }

    private function request(
        string $method,
        string $path,
        ?array $body = null,
        ?string $token = null
    ): Response {
        $headers = [];
        if ($token !== null) {
            $headers['authorization'] = 'Bearer ' . $token;
        }
        if ($body !== null) {
            $headers['content-type'] = 'application/json';
        }

        $request = new Request(
            $method,
            $path,
            $headers,
            $body === null ? '' : json_encode($body, JSON_THROW_ON_ERROR),
            '127.0.0.1'
        );

        return ApplicationFactory::kernel($this->connection)->handle($request);
    }
}
