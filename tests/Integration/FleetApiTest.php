<?php

declare(strict_types=1);

namespace UyuniTour\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use UyuniTour\Domain\Identity\Role;
use UyuniTour\Infrastructure\Bootstrap\ApplicationFactory;
use UyuniTour\Infrastructure\Database\PdoConnectionFactory;
use UyuniTour\Infrastructure\Database\PdoTourRepository;
use UyuniTour\Infrastructure\Database\PdoUserRepository;
use UyuniTour\Infrastructure\Http\Request;
use UyuniTour\Infrastructure\Http\Response;
use UyuniTour\Infrastructure\Security\PasswordHasher;

final class FleetApiTest extends TestCase
{
    private PDO $connection;
    private PdoUserRepository $users;
    private PdoTourRepository $tours;
    private PasswordHasher $passwords;
    private array $userIds = [];
    private array $tourIds = [];
    private array $departureIds = [];
    private array $vehicleIds = [];
    private array $touristIds = [];
    private array $reservationIds = [];
    private array $maintenanceIds = [];
    private array $incidentIds = [];
    private array $attemptHashes = [];
    private string $operatorToken;

    protected function setUp(): void
    {
        $this->connection = PdoConnectionFactory::createFromEnvironment(
            getenv('DB_TEST_DATABASE') ?: 'uyuni_tour_test'
        );
        $this->users = new PdoUserRepository($this->connection);
        $this->tours = new PdoTourRepository($this->connection);
        $this->passwords = new PasswordHasher();
        $this->operatorToken = $this->login(Role::Operator);
    }

    protected function tearDown(): void
    {
        foreach ($this->incidentIds as $id) {
            $this->deleteById('departure_incidents', $id);
        }
        foreach ($this->departureIds as $id) {
            $this->deleteById('departure_assignment_history', null, 'departure_id', $id);
            $this->deleteById('departure_assignments', null, 'departure_id', $id);
            $this->deleteById('departure_incidents', null, 'departure_id', $id);
            $this->deleteById('reservations', null, 'departure_id', $id);
        }
        foreach ($this->maintenanceIds as $id) {
            $this->deleteById('vehicle_maintenance', $id);
        }
        foreach ($this->touristIds as $id) {
            $this->deleteById('tourists', $id);
        }
        foreach ($this->departureIds as $id) {
            $this->deleteById('departures', $id);
        }
        foreach ($this->vehicleIds as $id) {
            $this->deleteById('vehicles', $id);
        }
        foreach ($this->tourIds as $id) {
            $this->deleteById('tours', $id);
        }
        foreach ($this->userIds as $id) {
            $this->deleteById('audit_events', null, 'actor_id', $id);
            $this->deleteById('users', $id);
        }
        foreach ($this->attemptHashes as $hash) {
            $statement = $this->connection->prepare(
                'DELETE FROM login_attempts WHERE identifier_hash = :hash'
            );
            $statement->execute(['hash' => $hash]);
        }
    }

    public function testAssignmentsPreventVehicleAndDriverOverlapAndAllowAdjacentIntervals(): void
    {
        $tourId = $this->createTour();
        $first = $this->createDeparture($tourId, '2038-06-01T08:00', '2038-06-01T10:00', 8);
        $adjacent = $this->createDeparture($tourId, '2038-06-01T10:00', '2038-06-01T12:00', 8);
        $overlap = $this->createDeparture($tourId, '2038-06-01T09:00', '2038-06-01T11:00', 8);
        $tooLarge = $this->createDeparture($tourId, '2038-06-01T15:00', '2038-06-01T17:00', 11);
        $vehicleId = $this->createVehicle('E4-VAN-1', 10);
        $secondVehicleId = $this->createVehicle('E4-VAN-2', 10);
        $driverId = $this->createDriver();
        $secondDriverId = $this->createDriver();

        self::assertSame(200, $this->assign($first, $vehicleId, $driverId)->status);
        self::assertSame(200, $this->assign($adjacent, $vehicleId, $driverId)->status);
        self::assertSame(409, $this->assign($overlap, $vehicleId, $driverId)->status);
        self::assertSame(409, $this->assign($overlap, $secondVehicleId, $driverId)->status);
        self::assertSame(200, $this->assign($overlap, $secondVehicleId, $secondDriverId)->status);
        self::assertSame(409, $this->assign($tooLarge, $vehicleId, $driverId)->status);

        self::assertSame(409, $this->request(
            'PATCH',
            '/api/v1/departures/' . $adjacent,
            ['start_at' => '2038-06-01T09:30', 'end_at' => '2038-06-01T11:30'],
            $this->operatorToken
        )->status);
        self::assertSame(200, $this->request(
            'PATCH',
            '/api/v1/departures/' . $adjacent,
            ['start_at' => '2038-06-01T12:00', 'end_at' => '2038-06-01T14:00'],
            $this->operatorToken
        )->status);

        self::assertSame(409, $this->request(
            'PATCH',
            '/api/v1/vehicles/' . $vehicleId,
            ['capacity' => 7],
            $this->operatorToken
        )->status);
        $inactive = $this->request(
            'PATCH',
            '/api/v1/vehicles/' . $vehicleId,
            ['status' => 'inactivo'],
            $this->operatorToken
        );
        self::assertSame(200, $inactive->status);
        self::assertSame(409, $this->request(
            'PATCH',
            '/api/v1/departures/' . $first,
            ['status' => 'en_curso'],
            $this->operatorToken
        )->status);
    }

    public function testReplacingAssignmentPreservesPreviousResourcesInHistory(): void
    {
        $tourId = $this->createTour();
        $departureId = $this->createDeparture($tourId, '2038-05-01T08:00', '2038-05-01T10:00', 4);
        $firstVehicle = $this->createVehicle('E4-HISTORY-1', 4);
        $secondVehicle = $this->createVehicle('E4-HISTORY-2', 4);
        $firstDriver = $this->createDriver();
        $secondDriver = $this->createDriver();
        self::assertSame(200, $this->assign($departureId, $firstVehicle, $firstDriver)->status);
        self::assertSame(200, $this->assign($departureId, $secondVehicle, $secondDriver)->status);

        $assignments = $this->request('GET', '/api/v1/assignments', null, $this->operatorToken);
        self::assertSame(200, $assignments->status);
        $assignment = current(array_filter(
            $assignments->body['data'],
            static fn (array $item): bool => $item['departure_id'] === $departureId
        ));
        self::assertSame($secondVehicle, $assignment['vehicle_id']);
        self::assertCount(1, $assignment['history']);
        self::assertSame($firstVehicle, $assignment['history'][0]['vehicle_id']);
        self::assertSame($firstDriver, $assignment['history'][0]['driver_user_id']);
        self::assertNotEmpty($assignment['history'][0]['replaced_at']);
    }

    public function testMaintenanceCannotOverlapAssignmentsAndBlocksLaterAssignment(): void
    {
        $tourId = $this->createTour();
        $assigned = $this->createDeparture($tourId, '2038-07-01T08:00', '2038-07-01T10:00', 6);
        $blocked = $this->createDeparture($tourId, '2038-07-01T11:00', '2038-07-01T13:00', 6);
        $adjacent = $this->createDeparture($tourId, '2038-07-01T12:00', '2038-07-01T14:00', 6);
        $rescheduled = $this->createDeparture($tourId, '2038-07-01T14:00', '2038-07-01T16:00', 6);
        $vehicleId = $this->createVehicle('E4-MAINT-1', 8);
        $driverId = $this->createDriver();
        self::assertSame(200, $this->assign($assigned, $vehicleId, $driverId)->status);
        self::assertSame(200, $this->assign($rescheduled, $vehicleId, $driverId)->status);

        self::assertSame(409, $this->request(
            'POST',
            '/api/v1/vehicles/' . $vehicleId . '/maintenance',
            [
                'start_at' => '2038-07-01T09:30',
                'end_at' => '2038-07-01T10:30',
                'description' => 'Revisión incompatible',
            ],
            $this->operatorToken
        )->status);
        $maintenance = $this->request(
            'POST',
            '/api/v1/vehicles/' . $vehicleId . '/maintenance',
            [
                'start_at' => '2038-07-01T10:00',
                'end_at' => '2038-07-01T12:00',
                'description' => 'Cambio de aceite',
            ],
            $this->operatorToken
        );
        self::assertSame(201, $maintenance->status);
        $maintenanceId = $maintenance->body['data']['id'];
        $this->maintenanceIds[] = $maintenanceId;
        self::assertSame(409, $this->assign($blocked, $vehicleId, $driverId)->status);
        self::assertSame(200, $this->assign($adjacent, $vehicleId, $driverId)->status);
        self::assertSame(409, $this->request(
            'PATCH',
            '/api/v1/departures/' . $rescheduled,
            ['start_at' => '2038-07-01T11:00', 'end_at' => '2038-07-01T13:00'],
            $this->operatorToken
        )->status);

        $status = $this->request(
            'PATCH',
            '/api/v1/maintenance/' . $maintenanceId,
            ['status' => 'en_curso'],
            $this->operatorToken
        );
        self::assertSame(200, $status->status);
        self::assertSame('en_curso', $status->body['data']['status']);
        self::assertSame(200, $this->request(
            'PATCH',
            '/api/v1/maintenance/' . $maintenanceId,
            ['status' => 'completado'],
            $this->operatorToken
        )->status);
        self::assertSame(409, $this->request(
            'PATCH',
            '/api/v1/maintenance/' . $maintenanceId,
            ['status' => 'cancelado'],
            $this->operatorToken
        )->status);
    }

    public function testStartingRequiresValidResourcesAndCapacityStillProtectsReservations(): void
    {
        $tourId = $this->createTour();
        $departureId = $this->createDeparture($tourId, '2038-08-01T08:00', '2038-08-01T10:00', 8);
        $vehicleId = $this->createVehicle('E4-START-1', 8);
        $driverId = $this->createDriver();
        self::assertSame(409, $this->request(
            'PATCH',
            '/api/v1/departures/' . $departureId,
            ['status' => 'en_curso'],
            $this->operatorToken
        )->status);
        self::assertSame(200, $this->assign($departureId, $vehicleId, $driverId)->status);

        $tourist = $this->request('POST', '/api/v1/tourists', [
            'full_name' => 'Pasajera E4',
        ], $this->operatorToken);
        self::assertSame(201, $tourist->status);
        $this->touristIds[] = $tourist->body['data']['id'];
        $reservation = $this->request(
            'POST',
            '/api/v1/reservations',
            [
                'departure_id' => $departureId,
                'tourist_id' => $tourist->body['data']['id'],
                'passengers' => ['Pasajera E4', 'Acompañante', 'Acompañante 2'],
                'price_per_person' => '50.00',
            ],
            $this->operatorToken,
            [],
            'e4-capacity-' . bin2hex(random_bytes(5))
        );
        self::assertSame(201, $reservation->status);
        $this->reservationIds[] = $reservation->body['data']['id'];
        self::assertSame(409, $this->request(
            'PATCH',
            '/api/v1/departures/' . $departureId,
            ['capacity' => 2],
            $this->operatorToken
        )->status);

        $started = $this->request(
            'PATCH',
            '/api/v1/departures/' . $departureId,
            ['status' => 'en_curso'],
            $this->operatorToken
        );
        self::assertSame(200, $started->status);
        self::assertSame('en_curso', $started->body['data']['status']);
    }

    public function testDriverOnlySeesOwnDeparturesPassengerNamesAndMayReportOwnIncident(): void
    {
        $tourId = $this->createTour();
        $own = $this->createDeparture($tourId, '2038-09-01T08:00', '2038-09-01T10:00', 4);
        $other = $this->createDeparture($tourId, '2038-09-02T08:00', '2038-09-02T10:00', 4);
        $vehicle = $this->createVehicle('E4-DRIVER-1', 4);
        $otherVehicle = $this->createVehicle('E4-DRIVER-2', 4);
        $driverId = $this->createDriver();
        $otherDriverId = $this->createDriver();
        self::assertSame(200, $this->assign($own, $vehicle, $driverId)->status);
        self::assertSame(200, $this->assign($other, $otherVehicle, $otherDriverId)->status);

        $tourist = $this->request('POST', '/api/v1/tourists', [
            'full_name' => 'Pasajero visible',
            'email' => 'privado@example.test',
        ], $this->operatorToken);
        self::assertSame(201, $tourist->status);
        $this->touristIds[] = $tourist->body['data']['id'];
        $reservation = $this->request(
            'POST',
            '/api/v1/reservations',
            [
                'departure_id' => $own,
                'tourist_id' => $tourist->body['data']['id'],
                'passengers' => ['Pasajero visible'],
                'price_per_person' => '30.00',
            ],
            $this->operatorToken,
            [],
            'e4-driver-' . bin2hex(random_bytes(5))
        );
        self::assertSame(201, $reservation->status);
        $this->reservationIds[] = $reservation->body['data']['id'];

        $driverToken = $this->loginForUser($driverId);
        $myDepartures = $this->request('GET', '/api/v1/my/departures', null, $driverToken);
        self::assertSame(200, $myDepartures->status);
        self::assertCount(1, $myDepartures->body['data']);
        self::assertSame($own, $myDepartures->body['data'][0]['id']);
        self::assertSame(['Pasajero visible'], $myDepartures->body['data'][0]['passengers']);
        self::assertArrayNotHasKey('tourist_email', $myDepartures->body['data'][0]);
        self::assertSame(403, $this->request('GET', '/api/v1/departures', null, $driverToken)->status);

        $incident = $this->request(
            'POST',
            '/api/v1/departures/' . $own . '/incidents',
            ['occurred_at' => '2038-09-01T09:00', 'description' => 'Demora breve por clima.'],
            $driverToken
        );
        self::assertSame(201, $incident->status);
        $this->incidentIds[] = $incident->body['data']['id'];
        self::assertSame(404, $this->request(
            'POST',
            '/api/v1/departures/' . $other . '/incidents',
            ['occurred_at' => '2038-09-02T09:00', 'description' => 'Incidencia ajena.'],
            $driverToken
        )->status);
        $visibleIncidents = $this->request('GET', '/api/v1/incidents', null, $driverToken);
        self::assertCount(1, $visibleIncidents->body['data']);
        self::assertSame(403, $this->request('GET', '/api/v1/vehicles', null, $driverToken)->status);
    }

    public function testConcurrentAssignmentsCannotDoubleBookOneVehicle(): void
    {
        $tourId = $this->createTour();
        $first = $this->createDeparture($tourId, '2038-10-01T08:00', '2038-10-01T10:00', 4);
        $second = $this->createDeparture($tourId, '2038-10-01T08:30', '2038-10-01T10:30', 4);
        $vehicle = $this->createVehicle('E4-CONCURRENT-1', 4);
        $drivers = [$this->createDriver(), $this->createDriver()];
        $operations = [];
        foreach ([$first, $second] as $index => $departureId) {
            $operations[] = [
                'departure_id' => $departureId,
                'vehicle_id' => $vehicle,
                'driver_user_id' => $drivers[$index],
            ];
        }
        $statuses = $this->runConcurrentOperations($operations);
        sort($statuses);
        self::assertSame([200, 409], $statuses);
        $count = $this->connection->prepare(
            'SELECT COUNT(*) FROM departure_assignments WHERE vehicle_id = :vehicle_id'
        );
        $count->execute(['vehicle_id' => $vehicle]);
        self::assertSame(1, (int) $count->fetchColumn());
    }

    public function testConcurrentAssignmentAndMaintenanceCannotOverlap(): void
    {
        $tourId = $this->createTour();
        $departureId = $this->createDeparture($tourId, '2038-11-01T08:00', '2038-11-01T10:00', 4);
        $vehicleId = $this->createVehicle('E4-CMAINT-1', 4);
        $driverId = $this->createDriver();

        $statuses = $this->runConcurrentOperations([
            [
                'departure_id' => $departureId,
                'vehicle_id' => $vehicleId,
                'driver_user_id' => $driverId,
            ],
            [
                'operation' => 'maintenance',
                'vehicle_id' => $vehicleId,
                'body' => [
                    'start_at' => '2038-11-01T09:00',
                    'end_at' => '2038-11-01T11:00',
                    'description' => 'Prueba concurrente de mantenimiento',
                ],
            ],
        ]);
        self::assertCount(1, array_intersect([200, 201], $statuses));
        self::assertContains(409, $statuses);

        $maintenance = $this->connection->prepare(
            'SELECT id FROM vehicle_maintenance WHERE vehicle_id = :vehicle_id'
        );
        $maintenance->execute(['vehicle_id' => $vehicleId]);
        $maintenanceId = $maintenance->fetchColumn();
        if ($maintenanceId !== false) {
            $this->maintenanceIds[] = (int) $maintenanceId;
        }
        $assignment = $this->connection->prepare(
            'SELECT COUNT(*) FROM departure_assignments WHERE departure_id = :departure_id'
        );
        $assignment->execute(['departure_id' => $departureId]);
        self::assertSame(1, (int) $assignment->fetchColumn() + ($maintenanceId !== false ? 1 : 0));
    }

    public function testAuditorCannotManageFleetAndDuplicatePlateConflicts(): void
    {
        $vehicleId = $this->createVehicle('E4-UNIQUE-1', 4);
        self::assertSame(409, $this->request(
            'POST',
            '/api/v1/vehicles',
            ['plate' => 'e4-unique-1', 'capacity' => 4],
            $this->operatorToken
        )->status);
        $auditorToken = $this->login(Role::Auditor);
        self::assertSame(403, $this->request('GET', '/api/v1/vehicles', null, $auditorToken)->status);
        self::assertSame(403, $this->request(
            'PATCH',
            '/api/v1/vehicles/' . $vehicleId,
            ['status' => 'unknown'],
            $auditorToken
        )->status);
    }

    private function runConcurrentOperations(array $operations): array
    {
        $startAt = microtime(true) + 1.0;
        $processes = [];
        foreach ($operations as $operation) {
            $pipes = [];
            $environment = getenv();
            $environment['UYUNI_TOKEN'] = $this->operatorToken;
            $environment['UYUNI_START_MICROTIME'] = (string) $startAt;
            $environment['DB_TEST_DATABASE'] = getenv('DB_TEST_DATABASE') ?: 'uyuni_tour_test';
            $process = proc_open(
                [PHP_BINARY, dirname(__DIR__) . '/Support/fleet-assignment-worker.php'],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                dirname(__DIR__, 2),
                $environment
            );
            self::assertIsResource($process);
            fwrite($pipes[0], json_encode($operation, JSON_THROW_ON_ERROR));
            fclose($pipes[0]);
            $processes[] = [$process, $pipes[1], $pipes[2]];
        }

        $statuses = [];
        foreach ($processes as [$process, $stdout, $stderr]) {
            $result = stream_get_contents($stdout);
            $error = stream_get_contents($stderr);
            fclose($stdout);
            fclose($stderr);
            self::assertSame(0, proc_close($process), $error);
            [$status, $errorCode] = array_pad(explode(':', $result, 3), 2, '');
            $statuses[] = (int) $status;
            self::assertTrue(
                (int) $status === 409
                    ? $errorCode === 'conflict'
                    : $errorCode === '',
                $result . ' ' . trim($error)
            );
        }

        return $statuses;
    }

    private function createTour(): int
    {
        $tour = $this->request('POST', '/api/v1/tours', [
            'name' => 'Tour E4 ' . bin2hex(random_bytes(3)),
            'description' => 'Descripción de prueba',
            'itinerary' => 'Uyuni',
            'duration_days' => 1,
            'base_price' => '100.00',
        ], $this->operatorToken);
        self::assertSame(201, $tour->status);
        $id = $tour->body['data']['id'];
        $this->tourIds[] = $id;

        return $id;
    }

    private function createDeparture(int $tourId, string $startAt, string $endAt, int $capacity): int
    {
        $departure = $this->request(
            'POST',
            '/api/v1/tours/' . $tourId . '/departures',
            ['start_at' => $startAt, 'end_at' => $endAt, 'capacity' => $capacity],
            $this->operatorToken
        );
        self::assertSame(201, $departure->status);
        $id = $departure->body['data']['id'];
        $this->departureIds[] = $id;

        return $id;
    }

    private function createVehicle(string $plate, int $capacity): int
    {
        $response = $this->request(
            'POST',
            '/api/v1/vehicles',
            ['plate' => $plate, 'capacity' => $capacity],
            $this->operatorToken
        );
        self::assertSame(201, $response->status);
        $id = $response->body['data']['id'];
        $this->vehicleIds[] = $id;

        return $id;
    }

    private function createDriver(): int
    {
        $user = $this->users->create(
            bin2hex(random_bytes(8)) . '@example.test',
            $this->passwords->hash('E4-test-password-1'),
            Role::DriverGuide
        );
        $this->userIds[] = $user->id;

        return $user->id;
    }

    private function assign(int $departureId, int $vehicleId, int $driverId): Response
    {
        return $this->request(
            'PUT',
            '/api/v1/departures/' . $departureId . '/assignment',
            ['vehicle_id' => $vehicleId, 'driver_user_id' => $driverId],
            $this->operatorToken
        );
    }

    private function login(Role $role): string
    {
        $email = bin2hex(random_bytes(8)) . '@example.test';
        $user = $this->users->create(
            $email,
            $this->passwords->hash('E4-test-password-1'),
            $role
        );
        $this->userIds[] = $user->id;
        $this->attemptHashes[] = hash('sha256', "email\0" . $email);
        $this->attemptHashes[] = hash('sha256', "ip\0" . '127.0.0.1');

        $response = $this->request('POST', '/api/v1/auth/login', [
            'email' => $email,
            'password' => 'E4-test-password-1',
        ]);
        self::assertSame(200, $response->status);
        return $response->body['data']['token'];
    }

    private function loginForUser(int $userId): string
    {
        $user = $this->users->findById($userId);
        self::assertNotNull($user);
        $record = $this->connection->prepare('SELECT email FROM users WHERE id = :id');
        $record->execute(['id' => $userId]);
        $email = (string) $record->fetchColumn();
        $this->attemptHashes[] = hash('sha256', "email\0" . $email);

        $response = $this->request('POST', '/api/v1/auth/login', [
            'email' => $email,
            'password' => 'E4-test-password-1',
        ]);
        self::assertSame(200, $response->status);

        return $response->body['data']['token'];
    }

    private function request(
        string $method,
        string $path,
        ?array $body = null,
        ?string $token = null,
        array $query = [],
        ?string $idempotencyKey = null
    ): Response {
        $headers = [];
        if ($token !== null) {
            $headers['authorization'] = 'Bearer ' . $token;
        }
        if ($idempotencyKey !== null) {
            $headers['idempotency-key'] = $idempotencyKey;
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

    private function deleteById(
        string $table,
        ?int $id,
        string $column = 'id',
        ?int $value = null
    ): void {
        $query = $this->connection->prepare('DELETE FROM ' . $table . ' WHERE ' . $column . ' = :value');
        $query->execute(['value' => $id ?? $value]);
    }
}
