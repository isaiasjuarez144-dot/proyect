<?php

declare(strict_types=1);

namespace UyuniTour\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use UyuniTour\Domain\Audit\AuditRepository;
use UyuniTour\Domain\Identity\Role;
use UyuniTour\Infrastructure\Database\PdoConnectionFactory;
use UyuniTour\Infrastructure\Database\PdoTourRepository;
use UyuniTour\Infrastructure\Database\PdoUserRepository;
use UyuniTour\Infrastructure\Http\Request;
use UyuniTour\Infrastructure\Http\Response;
use UyuniTour\Infrastructure\Security\PasswordHasher;
use UyuniTour\Infrastructure\Bootstrap\ApplicationFactory;

final class AuditApiTest extends TestCase
{
    private PDO $connection;
    private PdoUserRepository $users;
    private PasswordHasher $passwords;
    private array $userIds = [];
    private array $tourIds = [];
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
        foreach ($this->tourIds as $id) {
            $statement = $this->connection->prepare('DELETE FROM tours WHERE id = :id');
            $statement->execute(['id' => $id]);
        }
        foreach ($this->userIds as $id) {
            $audit = $this->connection->prepare('DELETE FROM audit_events WHERE actor_id = :id');
            $audit->execute(['id' => $id]);
            $statement = $this->connection->prepare('DELETE FROM users WHERE id = :id');
            $statement->execute(['id' => $id]);
        }
        foreach ($this->attemptHashes as $hash) {
            $statement = $this->connection->prepare(
                'DELETE FROM login_attempts WHERE identifier_hash = :hash'
            );
            $statement->execute(['hash' => $hash]);
        }
    }

    public function testAuditRecordsChangesFiltersPaginatesAndProtectsReads(): void
    {
        $admin = $this->createUser(Role::Administrator, 'audit-admin-password');
        $operator = $this->createUser(Role::Operator, 'audit-operator-password');
        $auditor = $this->createUser(Role::Auditor, 'audit-auditor-password');
        $adminToken = $this->login($admin->email, 'audit-admin-password');
        $auditorToken = $this->login($auditor->email, 'audit-auditor-password');
        $operatorToken = $this->login($operator->email, 'audit-operator-password');

        $created = $this->request('POST', '/api/v1/tours', [
            'name' => 'Auditoría Salar',
            'description' => 'Tour de prueba.',
            'itinerary' => 'Uyuni y Colchani.',
            'duration_days' => 1,
            'base_price' => '100.00',
        ], $adminToken);
        self::assertSame(201, $created->status);
        $tourId = $created->body['data']['id'];
        $this->tourIds[] = $tourId;

        $updated = $this->request('PATCH', '/api/v1/tours/' . $tourId, [
            'base_price' => '120.00',
        ], $adminToken);
        self::assertSame(200, $updated->status);

        $filtered = $this->request(
            'GET',
            '/api/v1/audit',
            null,
            $adminToken,
            [
                'entity' => 'tour',
                'action' => 'updated',
                'actor_id' => (string) $admin->id,
                'page' => '1',
                'per_page' => '1',
            ]
        );
        self::assertSame(200, $filtered->status);
        self::assertSame(1, $filtered->body['data']['total']);
        self::assertSame(1, $filtered->body['data']['pages']);
        self::assertSame('tour', $filtered->body['data']['items'][0]['entity']);
        self::assertSame($tourId, $filtered->body['data']['items'][0]['entity_id']);
        self::assertSame('100.00', $filtered->body['data']['items'][0]['old_values']['base_price']);
        self::assertSame('120.00', $filtered->body['data']['items'][0]['new_values']['base_price']);
        self::assertSame($admin->email, $filtered->body['data']['items'][0]['actor']);

        self::assertSame(200, $this->request('GET', '/api/v1/audit', null, $auditorToken)->status);
        self::assertSame(403, $this->request('GET', '/api/v1/audit', null, $operatorToken)->status);
        self::assertSame(422, $this->request(
            'GET',
            '/api/v1/audit',
            null,
            $adminToken,
            ['from' => '2030-02-01', 'to' => '2030-01-01']
        )->status);
        self::assertSame(422, $this->request(
            'GET',
            '/api/v1/audit',
            null,
            $adminToken,
            ['entity' => 'secret']
        )->status);
    }

    public function testFailedAuditRollsBackBusinessChangeAndLeavesNoSuccessEvent(): void
    {
        $admin = $this->createUser(Role::Administrator, 'audit-rollback-password');
        $before = (int) $this->connection->query(
            'SELECT COUNT(*) FROM tours WHERE name = \'Rollback audit tour\''
        )->fetchColumn();
        $failingAudit = new class implements AuditRepository {
            public function record(
                int $actorId,
                string $action,
                string $entity,
                int $entityId,
                ?array $oldValues,
                ?array $newValues
            ): void {
                throw new \RuntimeException('audit write failed');
            }

            public function search(
                ?string $from,
                ?string $to,
                ?string $entity,
                ?string $action,
                ?int $actorId,
                int $page,
                int $perPage
            ): array {
                return [];
            }
        };

        $repository = new PdoTourRepository($this->connection, $failingAudit);
        $this->expectException(\RuntimeException::class);
        try {
            $repository->create(
                $admin->id,
                'Rollback audit tour',
                'No debe persistir.',
                'Sin cambios.',
                1,
                '1.00'
            );
        } finally {
            $after = (int) $this->connection->query(
                'SELECT COUNT(*) FROM tours WHERE name = \'Rollback audit tour\''
            )->fetchColumn();
            self::assertSame($before, $after);
            self::assertSame(0, (int) $this->connection->query(
                'SELECT COUNT(*) FROM audit_events WHERE entity = \'tour\' AND new_values LIKE \'%No debe persistir.%\''
            )->fetchColumn());
        }
    }

    private function createUser(Role $role, string $password): \UyuniTour\Domain\Identity\User
    {
        $email = bin2hex(random_bytes(8)) . '@example.test';
        $user = $this->users->create($email, $this->passwords->hash($password), $role);
        $this->userIds[] = $user->id;
        $this->attemptHashes[] = hash('sha256', "email\0" . $email);
        $this->attemptHashes[] = hash('sha256', "ip\0" . '127.0.0.1');

        return $user;
    }

    private function login(string $email, string $password): string
    {
        $response = $this->request('POST', '/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);
        self::assertSame(200, $response->status);

        return $response->body['data']['token'];
    }

    private function request(
        string $method,
        string $path,
        ?array $body = null,
        ?string $token = null,
        array $query = []
    ): Response {
        $headers = [];
        if ($token !== null) {
            $headers['authorization'] = 'Bearer ' . $token;
        }
        if ($body !== null) {
            $headers['content-type'] = 'application/json';
        }

        return ApplicationFactory::kernel($this->connection)->handle(new Request(
            $method,
            $path,
            $headers,
            $body === null ? '' : json_encode($body, JSON_THROW_ON_ERROR),
            '127.0.0.1',
            $query
        ));
    }
}
