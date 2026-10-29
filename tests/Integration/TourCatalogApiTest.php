<?php

declare(strict_types=1);

namespace UyuniTour\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use UyuniTour\Domain\Identity\Role;
use UyuniTour\Domain\Identity\User;
use UyuniTour\Infrastructure\Bootstrap\ApplicationFactory;
use UyuniTour\Infrastructure\Database\PdoConnectionFactory;
use UyuniTour\Infrastructure\Database\PdoTourRepository;
use UyuniTour\Infrastructure\Database\PdoUserRepository;
use UyuniTour\Infrastructure\Http\Request;
use UyuniTour\Infrastructure\Http\Response;
use UyuniTour\Infrastructure\Security\PasswordHasher;

final class TourCatalogApiTest extends TestCase
{
    private PDO $connection;
    private PdoUserRepository $users;
    private PdoTourRepository $tours;
    private PasswordHasher $passwords;
    private array $createdUserIds = [];
    private array $createdTourIds = [];
    private array $createdDepartureIds = [];
    private array $createdVehicleIds = [];
    private array $attemptHashes = [];

    protected function setUp(): void
    {
        $database = getenv('DB_TEST_DATABASE') ?: 'uyuni_tour_test';
        $this->connection = PdoConnectionFactory::createFromEnvironment($database);
        $this->users = new PdoUserRepository($this->connection);
        $this->tours = new PdoTourRepository($this->connection);
        $this->passwords = new PasswordHasher();
    }

    protected function tearDown(): void
    {
        foreach ($this->createdDepartureIds as $id) {
            $statement = $this->connection->prepare(
                'DELETE FROM departure_assignments WHERE departure_id = :id'
            );
            $statement->execute(['id' => $id]);
            $statement = $this->connection->prepare('DELETE FROM departures WHERE id = :id');
            $statement->execute(['id' => $id]);
        }
        foreach ($this->createdVehicleIds as $id) {
            $statement = $this->connection->prepare('DELETE FROM vehicles WHERE id = :id');
            $statement->execute(['id' => $id]);
        }
        foreach ($this->createdTourIds as $id) {
            $statement = $this->connection->prepare('DELETE FROM tours WHERE id = :id');
            $statement->execute(['id' => $id]);
        }
        foreach ($this->createdUserIds as $id) {
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

    public function testOperatorCanCreateEditListAndDeactivateTours(): void
    {
        $token = $this->login(Role::Operator);
        $created = $this->request('POST', '/api/v1/tours', [
            'name' => 'Salar y lagunas',
            'description' => 'Recorrido por el Salar de Uyuni.',
            'itinerary' => 'Uyuni, Colchani, Incahuasi.',
            'duration_days' => 2,
            'base_price' => '1250.50',
        ], $token);

        self::assertSame(201, $created->status);
        self::assertSame('1250.50', $created->body['data']['base_price']);
        self::assertSame(2, $created->body['data']['duration_days']);
        $tourId = $created->body['data']['id'];
        $this->createdTourIds[] = $tourId;

        $updated = $this->request('PATCH', '/api/v1/tours/' . $tourId, [
            'name' => 'Salar de Uyuni',
            'base_price' => 1350,
        ], $token);
        self::assertSame(200, $updated->status);
        self::assertSame('Salar de Uyuni', $updated->body['data']['name']);
        self::assertSame('1350.00', $updated->body['data']['base_price']);
        self::assertSame('Recorrido por el Salar de Uyuni.', $updated->body['data']['description']);

        $listed = $this->request('GET', '/api/v1/tours', null, $token);
        self::assertSame(200, $listed->status);
        self::assertSame($tourId, $listed->body['data'][0]['id']);

        $deactivated = $this->request(
            'PATCH',
            '/api/v1/tours/' . $tourId,
            ['active' => false],
            $token
        );
        self::assertSame(200, $deactivated->status);
        self::assertFalse($deactivated->body['data']['active']);
        self::assertSame(422, $this->request(
            'POST',
            '/api/v1/tours/' . $tourId . '/departures',
            [
                'start_at' => '2030-05-01T08:00',
                'end_at' => '2030-05-01T12:00',
                'capacity' => 8,
            ],
            $token
        )->status);
    }

    public function testDepartureFiltersAndValidStatusTransitions(): void
    {
        $token = $this->login(Role::Administrator);
        $tourId = $this->createTour($token);
        $created = $this->request('POST', '/api/v1/tours/' . $tourId . '/departures', [
            'start_at' => '2030-05-01T08:00',
            'end_at' => '2030-05-02T12:00',
            'capacity' => 12,
        ], $token);
        self::assertSame(201, $created->status);
        self::assertSame('programada', $created->body['data']['status']);
        self::assertSame('2030-05-01 08:00:00', $created->body['data']['start_at']);
        self::assertSame(12, $created->body['data']['capacity']);
        $departureId = $created->body['data']['id'];
        $this->createdDepartureIds[] = $departureId;

        $vehicle = $this->request('POST', '/api/v1/vehicles', [
            'plate' => 'E2-START-' . strtoupper(bin2hex(random_bytes(3))),
            'capacity' => 12,
        ], $token);
        self::assertSame(201, $vehicle->status);
        $vehicleId = $vehicle->body['data']['id'];
        $this->createdVehicleIds[] = $vehicleId;
        $driver = $this->users->create(
            bin2hex(random_bytes(8)) . '@example.test',
            $this->passwords->hash('E2-test-password-1'),
            Role::DriverGuide
        );
        $this->createdUserIds[] = $driver->id;
        self::assertSame(200, $this->request(
            'PUT',
            '/api/v1/departures/' . $departureId . '/assignment',
            ['vehicle_id' => $vehicleId, 'driver_user_id' => $driver->id],
            $token
        )->status);

        $filtered = $this->request(
            'GET',
            '/api/v1/departures',
            null,
            $token,
            ['from' => '2030-05-01', 'to' => '2030-05-01', 'status' => 'programada', 'tour_id' => $tourId]
        );
        self::assertSame(200, $filtered->status);
        self::assertCount(1, $filtered->body['data']);
        self::assertSame('Salar itinerario', $filtered->body['data'][0]['tour_name']);

        self::assertSame('en_curso', $this->changeStatus($departureId, 'en_curso', $token));
        self::assertSame('finalizada', $this->changeStatus($departureId, 'finalizada', $token));
        self::assertSame(422, $this->request(
            'PATCH',
            '/api/v1/departures/' . $departureId,
            ['status' => 'cancelada'],
            $token
        )->status);

        $completed = $this->request(
            'GET',
            '/api/v1/departures',
            null,
            $token,
            ['status' => 'finalizada', 'from' => '2030-05-01']
        );
        self::assertSame(200, $completed->status);
        self::assertCount(1, $completed->body['data']);
        self::assertSame('finalizada', $completed->body['data'][0]['status']);
        self::assertSame(422, $this->request(
            'GET',
            '/api/v1/departures',
            null,
            $token,
            ['from' => '2030-05-03', 'to' => '2030-05-01']
        )->status);
    }

    public function testDepartureDatesRequireDatabaseRangeAndRealCalendarValues(): void
    {
        $token = $this->login(Role::Operator);
        $tourId = $this->createTour($token);
        $validEnd = '2030-07-01T12:00';

        foreach (
            [
            '222222-07-01T08:00',
            '0999-07-01T08:00',
            '2030-02-30T08:00',
            '2030-7-01T08:00',
            ] as $invalidStart
        ) {
            $response = $this->request(
                'POST',
                '/api/v1/tours/' . $tourId . '/departures',
                [
                    'start_at' => $invalidStart,
                    'end_at' => $validEnd,
                    'capacity' => 4,
                ],
                $token
            );
            self::assertSame(422, $response->status);
            self::assertSame('start_at', $response->body['error']['field']);
            self::assertStringContainsString('1000 y 9999', $response->body['error']['message']);
            self::assertStringNotContainsString('credenciales', $response->body['error']['message']);
        }

        $invalidEnd = $this->request(
            'POST',
            '/api/v1/tours/' . $tourId . '/departures',
            [
                'start_at' => '2030-07-01T08:00',
                'end_at' => '2030-07-01T08:00',
                'capacity' => 4,
            ],
            $token
        );
        self::assertSame(422, $invalidEnd->status);
        self::assertSame('end_at', $invalidEnd->body['error']['field']);

        foreach (['222222-07-01', '0999-07-01', '2030-02-30', '2030-7-01'] as $invalidDate) {
            $response = $this->request(
                'GET',
                '/api/v1/departures',
                null,
                $token,
                ['from' => $invalidDate]
            );
            self::assertSame(422, $response->status);
            self::assertSame('from', $response->body['error']['field']);
            self::assertStringNotContainsString('credenciales', $response->body['error']['message']);
        }

        $inverted = $this->request(
            'GET',
            '/api/v1/departures',
            null,
            $token,
            ['from' => '2030-07-02', 'to' => '2030-07-01']
        );
        self::assertSame(422, $inverted->status);
        self::assertSame('from', $inverted->body['error']['field']);
        self::assertSame('Desde no puede ser posterior a Hasta.', $inverted->body['error']['message']);

        $upperBound = $this->request(
            'GET',
            '/api/v1/departures',
            null,
            $token,
            ['from' => '9999-12-31', 'to' => '9999-12-31']
        );
        self::assertSame(200, $upperBound->status);
    }

    public function testOnlyAdministratorsAndOperatorsCanAccessCatalogAndDepartures(): void
    {
        $auditorToken = $this->login(Role::Auditor);
        self::assertSame(403, $this->request('GET', '/api/v1/tours', null, $auditorToken)->status);
        self::assertSame(403, $this->request('GET', '/api/v1/departures', null, $auditorToken)->status);
        self::assertSame(401, $this->request('GET', '/api/v1/tours')->status);

        $operatorToken = $this->login(Role::Operator);
        $tourId = $this->createTour($operatorToken);
        $departure = $this->request('POST', '/api/v1/tours/' . $tourId . '/departures', [
            'start_at' => '2030-06-01T08:00',
            'end_at' => '2030-06-01T12:00',
            'capacity' => 5,
        ], $operatorToken);
        self::assertSame(201, $departure->status);
        $this->createdDepartureIds[] = $departure->body['data']['id'];
    }

    public function testValidationAndNoDeleteRouteProtectTourHistory(): void
    {
        $token = $this->login(Role::Operator);
        self::assertSame(422, $this->request('POST', '/api/v1/tours', [
            'name' => ' ',
            'description' => '',
            'itinerary' => '',
            'duration_days' => 0,
            'base_price' => '-1',
        ], $token)->status);

        $tourId = $this->createTour($token);
        $departure = $this->request('POST', '/api/v1/tours/' . $tourId . '/departures', [
            'start_at' => '2030-07-01T12:00',
            'end_at' => '2030-07-01T08:00',
            'capacity' => 0,
        ], $token);
        self::assertSame(422, $departure->status);
        $departure = $this->request('POST', '/api/v1/tours/' . $tourId . '/departures', [
            'start_at' => '2030-07-01T08:00',
            'end_at' => '2030-07-01T12:00',
            'capacity' => 2,
        ], $token);
        self::assertSame(201, $departure->status);
        $this->createdDepartureIds[] = $departure->body['data']['id'];

        self::assertSame(404, $this->request(
            'DELETE',
            '/api/v1/tours/' . $tourId,
            null,
            $token
        )->status);
        self::assertNotNull($this->tours->findById($tourId));

        $delete = $this->connection->prepare('DELETE FROM tours WHERE id = :id');
        try {
            $delete->execute(['id' => $tourId]);
            self::fail('A tour referenced by a departure must not be deletable.');
        } catch (\PDOException) {
            self::assertNotNull($this->tours->findById($tourId));
        }
    }

    private function createTour(string $token): int
    {
        $response = $this->request('POST', '/api/v1/tours', [
            'name' => 'Salar itinerario',
            'description' => 'Descripción del tour.',
            'itinerary' => 'Uyuni, Salar y retorno.',
            'duration_days' => 1,
            'base_price' => '100.00',
        ], $token);
        self::assertSame(201, $response->status);
        $this->createdTourIds[] = $response->body['data']['id'];

        return $response->body['data']['id'];
    }

    private function changeStatus(int $id, string $status, string $token): string
    {
        $response = $this->request(
            'PATCH',
            '/api/v1/departures/' . $id,
            ['status' => $status],
            $token
        );
        self::assertSame(200, $response->status);

        return $response->body['data']['status'];
    }

    private function login(Role $role): string
    {
        $email = bin2hex(random_bytes(8)) . '@example.test';
        $user = $this->users->create($email, $this->passwords->hash('E2-test-password-1'), $role);
        $this->createdUserIds[] = $user->id;
        $this->attemptHashes[] = hash('sha256', "email\0" . $email);
        $this->attemptHashes[] = hash('sha256', "ip\0" . '127.0.0.1');
        $response = $this->request('POST', '/api/v1/auth/login', [
            'email' => $email,
            'password' => 'E2-test-password-1',
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
        $request = new Request(
            $method,
            $path,
            $headers,
            $body === null ? '' : json_encode($body, JSON_THROW_ON_ERROR),
            '127.0.0.1',
            $query
        );

        return ApplicationFactory::kernel($this->connection)->handle($request);
    }
}
