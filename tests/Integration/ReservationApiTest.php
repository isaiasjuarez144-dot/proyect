<?php

declare(strict_types=1);

namespace UyuniTour\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use UyuniTour\Domain\Identity\Role;
use UyuniTour\Infrastructure\Bootstrap\ApplicationFactory;
use UyuniTour\Infrastructure\Database\PdoConnectionFactory;
use UyuniTour\Infrastructure\Database\PdoReservationRepository;
use UyuniTour\Infrastructure\Database\PdoTourRepository;
use UyuniTour\Infrastructure\Database\PdoUserRepository;
use UyuniTour\Infrastructure\Http\Request;
use UyuniTour\Infrastructure\Http\Response;
use UyuniTour\Infrastructure\Security\PasswordHasher;

final class ReservationApiTest extends TestCase
{
    private PDO $connection;
    private PdoUserRepository $users;
    private PdoTourRepository $tours;
    private PasswordHasher $passwords;
    private array $userIds = [];
    private array $tourIds = [];
    private array $departureIds = [];
    private array $touristIds = [];
    private array $reservationIds = [];
    private array $paymentIds = [];
    private array $attemptHashes = [];
    private int $actorId;

    protected function setUp(): void
    {
        $this->connection = PdoConnectionFactory::createFromEnvironment(
            getenv('DB_TEST_DATABASE') ?: 'uyuni_tour_test'
        );
        $this->users = new PdoUserRepository($this->connection);
        $this->tours = new PdoTourRepository($this->connection);
        $this->passwords = new PasswordHasher();
    }

    protected function tearDown(): void
    {
        foreach ($this->paymentIds as $id) {
            $statement = $this->connection->prepare('DELETE FROM payments WHERE id = :id');
            $statement->execute(['id' => $id]);
        }
        foreach ($this->reservationIds as $id) {
            $statement = $this->connection->prepare('DELETE FROM payments WHERE reservation_id = :id');
            $statement->execute(['id' => $id]);
            $statement = $this->connection->prepare('DELETE FROM reservations WHERE id = :id');
            $statement->execute(['id' => $id]);
        }

        foreach ($this->departureIds as $id) {
            $statement = $this->connection->prepare(
                'DELETE p FROM payments p INNER JOIN reservations r ON r.id = p.reservation_id '
                . 'WHERE r.departure_id = :id'
            );
            $statement->execute(['id' => $id]);
            $statement = $this->connection->prepare('DELETE FROM reservations WHERE departure_id = :id');
            $statement->execute(['id' => $id]);
        }
        foreach ($this->touristIds as $id) {
            $statement = $this->connection->prepare('DELETE FROM tourists WHERE id = :id');
            $statement->execute(['id' => $id]);
        }
        foreach ($this->departureIds as $id) {
            $statement = $this->connection->prepare('DELETE FROM departures WHERE id = :id');
            $statement->execute(['id' => $id]);
        }
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

    public function testPaymentsAreIdempotentCappedTraceableAndVisibleOnReservation(): void
    {
        $token = $this->login();
        $departureId = $this->createDeparture($token, 2);
        $tourist = $this->request('POST', '/api/v1/tourists', ['full_name' => 'Pagadora E5'], $token);
        $touristId = $tourist->body['data']['id'];
        $this->touristIds[] = $touristId;
        $reservation = $this->request(
            'POST',
            '/api/v1/reservations',
            [
                'departure_id' => $departureId,
                'tourist_id' => $touristId,
                'passengers' => ['Pagadora E5'],
                'price_per_person' => '100.00',
            ],
            $token,
            [],
            'e5-reserve-' . bin2hex(random_bytes(5))
        );
        $reservationId = $reservation->body['data']['id'];
        $this->reservationIds[] = $reservationId;
        self::assertSame('0.00', $reservation->body['data']['paid_net']);
        self::assertSame('100.00', $reservation->body['data']['balance_due']);

        $body = [
            'amount' => '40.10',
            'paid_at' => '2032-05-01T10:00',
            'method' => 'transferencia',
            'reference' => 'TRX-E5',
        ];
        $key = 'payment-' . bin2hex(random_bytes(6));
        $payment = $this->request(
            'POST',
            '/api/v1/reservations/' . $reservationId . '/payments',
            $body,
            $token,
            [],
            $key
        );
        self::assertSame(201, $payment->status);
        $paymentId = $payment->body['data']['id'];
        $this->paymentIds[] = $paymentId;
        self::assertSame($paymentId, $this->request(
            'POST',
            '/api/v1/reservations/' . $reservationId . '/payments',
            $body,
            $token,
            [],
            $key
        )->body['data']['id']);
        self::assertSame(409, $this->request(
            'POST',
            '/api/v1/reservations/' . $reservationId . '/payments',
            [...$body, 'amount' => '40.11'],
            $token,
            [],
            $key
        )->status);
        self::assertSame(409, $this->request(
            'POST',
            '/api/v1/reservations/' . $reservationId . '/payments',
            [...$body, 'amount' => '60.00'],
            $token,
            [],
            'payment-' . bin2hex(random_bytes(6))
        )->status);
        $listed = $this->request('GET', '/api/v1/reservations', null, $token);
        $record = current(array_filter(
            $listed->body['data'],
            static fn (array $item): bool => $item['id'] === $reservationId
        ));
        self::assertSame('40.10', $record['paid_net']);
        self::assertSame('59.90', $record['balance_due']);
        self::assertSame(403, $this->request(
            'POST',
            '/api/v1/payments/' . $paymentId . '/void',
            ['reason' => 'Corrección'],
            $token
        )->status);
        $adminToken = $this->login(Role::Administrator);
        $voided = $this->request(
            'POST',
            '/api/v1/payments/' . $paymentId . '/void',
            ['reason' => 'Corrección de referencia'],
            $adminToken
        );
        self::assertSame(200, $voided->status);
        self::assertSame('anulado', $voided->body['data']['status']);
        self::assertSame(409, $this->request(
            'POST',
            '/api/v1/payments/' . $paymentId . '/void',
            ['reason' => 'Segundo intento'],
            $adminToken
        )->status);
    }

    public function testConcurrentPaymentsCannotExceedReservationTotal(): void
    {
        $token = $this->login();
        $departureId = $this->createDeparture($token, 1);
        $tourist = $this->request('POST', '/api/v1/tourists', ['full_name' => 'Concurrent E5'], $token);
        $this->touristIds[] = $tourist->body['data']['id'];
        $reservation = $this->request(
            'POST',
            '/api/v1/reservations',
            [
                'departure_id' => $departureId,
                'tourist_id' => $tourist->body['data']['id'],
                'passengers' => ['Concurrent E5'],
                'price_per_person' => '100.00',
            ],
            $token,
            [],
            'e5-concurrent-reserve-' . bin2hex(random_bytes(5))
        );
        $reservationId = $reservation->body['data']['id'];
        $this->reservationIds[] = $reservationId;
        $startAt = microtime(true) + 1.0;
        $processes = [];
        foreach ([1, 2] as $index) {
            $pipes = [];
            $environment = getenv();
            $environment['UYUNI_TOKEN'] = $token;
            $environment['UYUNI_START_MICROTIME'] = (string) $startAt;
            $environment['DB_TEST_DATABASE'] = getenv('DB_TEST_DATABASE') ?: 'uyuni_tour_test';
            $process = proc_open(
                [PHP_BINARY, dirname(__DIR__) . '/Support/payment-worker.php'],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                dirname(__DIR__, 2),
                $environment
            );
            self::assertIsResource($process);
            fwrite($pipes[0], json_encode([
                'reservation_id' => $reservationId,
                'key' => 'e5-concurrent-payment-' . $index . '-' . bin2hex(random_bytes(3)),
                'body' => [
                    'amount' => '60.00',
                    'paid_at' => '2032-05-01T10:00',
                    'method' => 'efectivo',
                ],
            ], JSON_THROW_ON_ERROR));
            fclose($pipes[0]);
            $processes[] = [$process, $pipes[1], $pipes[2]];
        }

        $statuses = [];
        foreach ($processes as [$process, $stdout, $stderr]) {
            $statuses[] = (int) stream_get_contents($stdout);
            $error = stream_get_contents($stderr);
            fclose($stdout);
            fclose($stderr);
            self::assertSame(0, proc_close($process), $error);
        }
        sort($statuses);
        self::assertSame([201, 409], $statuses);
    }

    public function testReservationAndInitialPaymentCommitTogether(): void
    {
        $token = $this->login();
        $departureId = $this->createDeparture($token, 1);
        $tourist = $this->request('POST', '/api/v1/tourists', ['full_name' => 'Anticipo junto'], $token);
        $this->touristIds[] = $tourist->body['data']['id'];
        $created = $this->request(
            'POST',
            '/api/v1/reservations',
            [
                'departure_id' => $departureId,
                'tourist_id' => $tourist->body['data']['id'],
                'passengers' => ['Anticipo junto'],
                'price_per_person' => '100.00',
                'payment' => [
                    'amount' => '25.00',
                    'paid_at' => '2032-05-01T10:00',
                    'method' => 'qr',
                    'reference' => 'E5-JUNTO',
                    'idempotency_key' => 'e5-initial-' . bin2hex(random_bytes(4)),
                ],
            ],
            $token,
            [],
            'e5-checkout-' . bin2hex(random_bytes(5))
        );
        self::assertSame(201, $created->status);
        self::assertSame('25.00', $created->body['data']['paid_net']);
        self::assertSame('75.00', $created->body['data']['balance_due']);
    }

    public function testTouristsReservationsIdempotencyPriceAndSingleCancellation(): void
    {
        $token = $this->login();
        $departureId = $this->createDeparture($token, 3);
        $tourist = $this->request('POST', '/api/v1/tourists', [
            'full_name' => 'María Quispe',
            'email' => 'maria@example.test',
            'phone' => '+591 70000000',
        ], $token);
        self::assertSame(201, $tourist->status);
        $touristId = $tourist->body['data']['id'];
        $this->touristIds[] = $touristId;
        $editedTourist = $this->request('PATCH', '/api/v1/tourists/' . $touristId, [
            'full_name' => 'María Quispe Flores',
            'email' => 'maria@example.test',
            'phone' => null,
        ], $token);
        self::assertSame(200, $editedTourist->status);
        self::assertSame('María Quispe Flores', $editedTourist->body['data']['full_name']);
        $secondDeparture = $this->request(
            'POST',
            '/api/v1/tours/' . $this->tourIds[0] . '/departures',
            [
                'start_at' => '2032-05-02T08:00',
                'end_at' => '2032-05-02T12:00',
                'capacity' => 2,
            ],
            $token
        );
        self::assertSame(201, $secondDeparture->status);
        $secondDepartureId = $secondDeparture->body['data']['id'];
        $this->departureIds[] = $secondDepartureId;

        $body = [
            'departure_id' => $departureId,
            'tourist_id' => $touristId,
            'passengers' => ['María Quispe Flores', 'Luis Quispe'],
            'price_per_person' => '12.05',
        ];
        $key = 'reserve-' . bin2hex(random_bytes(8));
        $created = $this->request('POST', '/api/v1/reservations', $body, $token, [], $key);
        self::assertSame(201, $created->status);
        $reservationId = $created->body['data']['id'];
        $this->reservationIds[] = $reservationId;
        self::assertSame('12.05', $created->body['data']['price_per_person']);
        self::assertSame('24.10', $created->body['data']['total_price']);
        self::assertSame(2, $created->body['data']['passenger_count']);

        $replay = $this->request('POST', '/api/v1/reservations', $body, $token, [], $key);
        self::assertSame(201, $replay->status);
        self::assertSame($reservationId, $replay->body['data']['id']);
        self::assertSame(409, $this->request(
            'POST',
            '/api/v1/reservations',
            [...$body, 'passengers' => ['Otra persona']],
            $token,
            [],
            $key
        )->status);
        self::assertSame(409, $this->request('PATCH', '/api/v1/reservations/' . $reservationId, [
            'departure_id' => $secondDepartureId,
            'tourist_id' => $touristId,
            'passengers' => ['María Quispe Flores', 'Luis Quispe', 'Ana Quispe'],
        ], $token)->status);

        $tourPriceChange = $this->request(
            'PATCH',
            '/api/v1/tours/' . $this->tourIds[0],
            ['base_price' => '999.99'],
            $token
        );
        self::assertSame(200, $tourPriceChange->status);
        $updated = $this->request('PATCH', '/api/v1/reservations/' . $reservationId, [
            'departure_id' => $departureId,
            'tourist_id' => $touristId,
            'passengers' => ['María Quispe Flores', 'Luis Quispe', 'Ana Quispe'],
        ], $token);
        self::assertSame(200, $updated->status);
        self::assertSame('12.05', $updated->body['data']['price_per_person']);
        self::assertSame('36.15', $updated->body['data']['total_price']);

        self::assertSame(409, $this->request('PATCH', '/api/v1/departures/' . $departureId, [
            'capacity' => 2,
        ], $token)->status);
        $occupiedList = $this->request('GET', '/api/v1/departures', null, $token);
        $occupiedDeparture = array_values(array_filter(
            $occupiedList->body['data'],
            static fn (array $departure): bool => $departure['id'] === $departureId
        ));
        self::assertSame(3, $occupiedDeparture[0]['occupied_seats']);
        self::assertSame(0, $occupiedDeparture[0]['available_seats']);
        $cancelled = $this->request(
            'POST',
            '/api/v1/reservations/' . $reservationId . '/cancel',
            [],
            $token
        );
        self::assertSame(200, $cancelled->status);
        self::assertSame('cancelada', $cancelled->body['data']['status']);
        self::assertSame('cancelada', $this->request(
            'POST',
            '/api/v1/reservations/' . $reservationId . '/cancel',
            [],
            $token
        )->body['data']['status']);

        $listed = $this->request('GET', '/api/v1/departures', null, $token);
        $result = array_values(array_filter(
            $listed->body['data'],
            static fn (array $departure): bool => $departure['id'] === $departureId
        ));
        self::assertSame(0, $result[0]['occupied_seats']);
        self::assertSame(3, $result[0]['available_seats']);
    }

    public function testCapacityConflictRollsBackReservationAndPassengerRows(): void
    {
        $token = $this->login();
        $departureId = $this->createDeparture($token, 1);
        $touristId = $this->createTourist($token);
        $body = [
            'departure_id' => $departureId,
            'tourist_id' => $touristId,
            'passengers' => ['Pasajero Uno', 'Pasajero Dos'],
            'price_per_person' => '25.10',
        ];
        $before = (int) $this->connection->query('SELECT COUNT(*) FROM reservations')->fetchColumn();
        $passengersBefore = (int) $this->connection->query(
            'SELECT COUNT(*) FROM reservation_passengers'
        )->fetchColumn();
        $response = $this->request(
            'POST',
            '/api/v1/reservations',
            $body,
            $token,
            [],
            'rollback-' . bin2hex(random_bytes(6))
        );
        self::assertSame(409, $response->status);
        self::assertSame($before, (int) $this->connection->query('SELECT COUNT(*) FROM reservations')->fetchColumn());
        self::assertSame(
            $passengersBefore,
            (int) $this->connection->query('SELECT COUNT(*) FROM reservation_passengers')->fetchColumn()
        );
        self::assertSame(200, $this->request(
            'PATCH',
            '/api/v1/departures/' . $departureId,
            ['capacity' => 2],
            $token
        )->status);

        try {
            (new PdoReservationRepository($this->connection))->create(
                $this->actorId,
                'rollback-child-' . bin2hex(random_bytes(6)),
                hash('sha256', 'rollback-child'),
                $departureId,
                $touristId,
                ['Primer pasajero', null],
                2510
            );
            self::fail('A passenger insert failure must roll back its reservation transaction.');
        } catch (\PDOException) {
            self::assertSame(
                $before,
                (int) $this->connection->query('SELECT COUNT(*) FROM reservations')->fetchColumn()
            );
            self::assertSame(
                $passengersBefore,
                (int) $this->connection->query('SELECT COUNT(*) FROM reservation_passengers')->fetchColumn()
            );
        }
    }

    public function testTwoRealRequestsCompetingForLastSeatConfirmOnlyOne(): void
    {
        $token = $this->login();
        $departureId = $this->createDeparture($token, 1);
        $touristId = $this->createTourist($token);
        $startAt = microtime(true) + 1.5;
        $workers = [];
        for ($index = 0; $index < 2; $index++) {
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $environment = getenv();
            $environment['UYUNI_TOKEN'] = $token;
            $environment['UYUNI_START_MICROTIME'] = (string) $startAt;
            $process = proc_open(
                [PHP_BINARY, dirname(__DIR__) . '/Support/reservation-worker.php'],
                $descriptors,
                $pipes,
                dirname(__DIR__, 2),
                $environment
            );
            self::assertIsResource($process);
            fwrite($pipes[0], json_encode([
                'key' => 'parallel-' . $index . '-' . bin2hex(random_bytes(6)),
                'body' => [
                    'departure_id' => $departureId,
                    'tourist_id' => $touristId,
                    'passengers' => ['Viajero ' . $index],
                    'price_per_person' => '50.00',
                ],
            ], JSON_THROW_ON_ERROR));
            fclose($pipes[0]);
            $workers[] = [$process, $pipes[1], $pipes[2]];
        }

        $statuses = [];
        foreach ($workers as [$process, $stdout, $stderr]) {
            $statuses[] = (int) stream_get_contents($stdout);
            $error = stream_get_contents($stderr);
            fclose($stdout);
            fclose($stderr);
            self::assertSame(0, proc_close($process), $error);
        }
        sort($statuses);
        self::assertSame([201, 409], $statuses);
        $this->connection->prepare(
            'DELETE FROM reservations WHERE departure_id = :departure_id AND passenger_count = 1'
        )->execute(['departure_id' => $departureId]);
    }

    public function testAdministratorAndOperatorCanManageWhileAuditorIsDenied(): void
    {
        $administrator = $this->login(Role::Administrator);
        $tourist = $this->request('POST', '/api/v1/tourists', [
            'full_name' => 'Turista administrador',
        ], $administrator);
        self::assertSame(201, $tourist->status);
        $this->touristIds[] = $tourist->body['data']['id'];

        $auditor = $this->login(Role::Auditor);
        self::assertSame(403, $this->request('GET', '/api/v1/tourists', null, $auditor)->status);
        self::assertSame(403, $this->request('GET', '/api/v1/reservations', null, $auditor)->status);
    }

    public function testTouristValidationReturnsFieldErrorWithoutInvalidatingAuthenticatedSession(): void
    {
        $token = $this->login();
        $invalid = $this->request('POST', '/api/v1/tourists', [
            'full_name' => 'Turista válido',
            'email' => 'correo-no-valido',
        ], $token);
        self::assertSame(422, $invalid->status);
        self::assertSame('email', $invalid->body['error']['field']);

        $authenticated = $this->request('GET', '/api/v1/auth/me', null, $token);
        self::assertSame(200, $authenticated->status);

        $valid = $this->request('POST', '/api/v1/tourists', [
            'full_name' => 'Turista válido',
            'email' => 'turista-valido@example.test',
        ], $token);
        self::assertSame(201, $valid->status);
        $this->touristIds[] = $valid->body['data']['id'];
    }

    private function createDeparture(string $token, int $capacity): int
    {
        $tour = $this->request('POST', '/api/v1/tours', [
            'name' => 'Tour de reservas ' . bin2hex(random_bytes(3)),
            'description' => 'Descripción',
            'itinerary' => 'Uyuni',
            'duration_days' => 1,
            'base_price' => '100.00',
        ], $token);
        self::assertSame(201, $tour->status);
        $tourId = $tour->body['data']['id'];
        $this->tourIds[] = $tourId;
        $departure = $this->request('POST', '/api/v1/tours/' . $tourId . '/departures', [
            'start_at' => '2032-05-01T08:00',
            'end_at' => '2032-05-01T12:00',
            'capacity' => $capacity,
        ], $token);
        self::assertSame(201, $departure->status);
        $departureId = $departure->body['data']['id'];
        $this->departureIds[] = $departureId;

        return $departureId;
    }

    private function createTourist(string $token): int
    {
        $response = $this->request('POST', '/api/v1/tourists', [
            'full_name' => 'Turista de prueba',
        ], $token);
        self::assertSame(201, $response->status);
        $id = $response->body['data']['id'];
        $this->touristIds[] = $id;

        return $id;
    }

    private function login(Role $role = Role::Operator): string
    {
        $email = bin2hex(random_bytes(8)) . '@example.test';
        $user = $this->users->create($email, $this->passwords->hash('E3-test-password-1'), $role);
        $this->userIds[] = $user->id;
        $this->actorId = $user->id;
        $this->attemptHashes[] = hash('sha256', "email\0" . $email);
        $this->attemptHashes[] = hash('sha256', "ip\0" . '127.0.0.1');
        $response = $this->request('POST', '/api/v1/auth/login', [
            'email' => $email,
            'password' => 'E3-test-password-1',
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
}
