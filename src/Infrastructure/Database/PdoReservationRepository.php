<?php

declare(strict_types=1);

namespace UyuniTour\Infrastructure\Database;

use PDO;
use PDOException;
use Throwable;
use UyuniTour\Domain\Reservations\Reservation;
use UyuniTour\Domain\Reservations\ReservationRepository;
use UyuniTour\Domain\Reservations\ReservationStatus;
use UyuniTour\Domain\Audit\AuditRepository;
use UyuniTour\Domain\Shared\ConflictException;
use UyuniTour\Domain\Shared\NotFoundException;
use UyuniTour\Domain\Shared\ValidationException;

final class PdoReservationRepository implements ReservationRepository
{
    private const MAX_TOTAL_CENTS = 999999999999;

    public function __construct(
        private readonly PDO $connection,
        private readonly ?AuditRepository $audit = null
    ) {
    }

    public function create(
        int $actorId,
        string $idempotencyKey,
        string $payloadHash,
        int $departureId,
        int $touristId,
        array $passengers,
        int $priceCents,
        ?array $payment = null
    ): Reservation {
        try {
            $this->connection->beginTransaction();
            $existing = $this->findByIdempotencyKey($actorId, $idempotencyKey);
            if ($existing !== null) {
                self::assertSameRequest($existing['request_hash'], $payloadHash);
                $this->connection->commit();

                return $this->findById((int) $existing['id'])
                    ?? throw new NotFoundException('Reserva no encontrada.');
            }
            $departure = $this->lockDeparture($departureId);
            $existing = $this->findByIdempotencyKey($actorId, $idempotencyKey);
            if ($existing !== null) {
                self::assertSameRequest($existing['request_hash'], $payloadHash);
                $this->connection->commit();

                return $this->findById((int) $existing['id'])
                    ?? throw new NotFoundException('Reserva no encontrada.');
            }
            self::assertBookable($departure);
            $this->assertTouristExists($touristId);
            $totalCents = self::totalCents($priceCents, count($passengers));
            $this->assertCapacity($departure, count($passengers));
            $statement = $this->connection->prepare(
                'INSERT INTO reservations '
                . '(departure_id, tourist_id, created_by, passenger_count, price_per_person, total_price, '
                . 'idempotency_key, request_hash) '
                . 'VALUES (:departure_id, :tourist_id, :created_by, :passenger_count, :price, :total, :key, :hash)'
            );
            $statement->execute([
                'departure_id' => $departureId,
                'tourist_id' => $touristId,
                'created_by' => $actorId,
                'passenger_count' => count($passengers),
                'price' => self::formatCents($priceCents),
                'total' => self::formatCents($totalCents),
                'key' => $idempotencyKey,
                'hash' => $payloadHash,
            ]);
            $id = (int) $this->connection->lastInsertId();
            $this->insertPassengers($id, $passengers);
            $this->audit?->record(
                $actorId,
                'created',
                'reservation',
                $id,
                null,
                [
                    'departure_id' => $departureId,
                    'tourist_id' => $touristId,
                    'passenger_count' => count($passengers),
                    'total_price' => self::formatCents($totalCents),
                ]
            );
            if ($payment !== null) {
                $paymentStatement = $this->connection->prepare(
                    'INSERT INTO payments (reservation_id, amount, paid_at, method, reference, created_by, '
                    . 'idempotency_key, request_hash) VALUES (:reservation_id, :amount, :paid_at, :method, '
                    . ':reference, :created_by, :key, :hash)'
                );
                $paymentStatement->execute([
                    'reservation_id' => $id,
                    'amount' => self::formatCents($payment['amount_cents']),
                    'paid_at' => $payment['paid_at'],
                    'method' => $payment['method'],
                    'reference' => $payment['reference'],
                    'created_by' => $actorId,
                    'key' => $payment['idempotency_key'],
                    'hash' => hash('sha256', json_encode($payment, JSON_THROW_ON_ERROR)),
                ]);
                $this->audit?->record(
                    $actorId,
                    'created',
                    'payment',
                    (int) $this->connection->lastInsertId(),
                    null,
                    ['reservation_id' => $id, 'amount' => self::formatCents($payment['amount_cents']),
                        'method' => $payment['method']]
                );
            }
            $this->connection->commit();

            return $this->findById($id) ?? throw new NotFoundException('Reserva no encontrada.');
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            if ($exception instanceof PDOException && $exception->getCode() === '23000') {
                $existing = $this->findByIdempotencyKey($actorId, $idempotencyKey);
                if ($existing !== null) {
                    self::assertSameRequest($existing['request_hash'], $payloadHash);

                    return $this->findById((int) $existing['id'])
                        ?? throw new NotFoundException('Reserva no encontrada.');
                }
            }
            throw $exception;
        }
    }

    public function update(
        int $actorId,
        int $id,
        int $departureId,
        int $touristId,
        array $passengers
    ): Reservation {
        $this->connection->beginTransaction();
        try {
            $current = $this->lockReservation($id);
            if ($current['status'] !== ReservationStatus::Confirmed->value) {
                throw new ConflictException('No se puede modificar una reserva cancelada.');
            }
            $departureIds = array_unique([(int) $current['departure_id'], $departureId]);
            sort($departureIds, SORT_NUMERIC);
            $departures = [];
            foreach ($departureIds as $lockedId) {
                $departures[$lockedId] = $this->lockDeparture($lockedId);
            }
            $target = $departures[$departureId];
            self::assertBookable($target);
            $this->assertTouristExists($touristId);
            $count = count($passengers);
            $totalCents = self::totalCents(
                self::parseCents((string) $current['price_per_person']),
                $count
            );
            $this->assertCapacity($target, $count, $id);
            $statement = $this->connection->prepare(
                'UPDATE reservations SET departure_id = :departure_id, tourist_id = :tourist_id, '
                . 'passenger_count = :passenger_count, total_price = :total WHERE id = :id'
            );
            $statement->execute([
                'departure_id' => $departureId,
                'tourist_id' => $touristId,
                'passenger_count' => $count,
                'total' => self::formatCents($totalCents),
                'id' => $id,
            ]);
            $this->replacePassengers($id, $passengers);
            $this->audit?->record(
                $actorId,
                'updated',
                'reservation',
                $id,
                [
                    'departure_id' => (int) $current['departure_id'],
                    'passenger_count' => (int) $current['passenger_count'],
                ],
                ['departure_id' => $departureId, 'passenger_count' => $count],
            );
            $this->connection->commit();

            return $this->findById($id) ?? throw new NotFoundException('Reserva no encontrada.');
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function cancel(int $actorId, int $id): Reservation
    {
        $this->connection->beginTransaction();
        try {
            $current = $this->lockReservation($id);
            $this->lockDeparture((int) $current['departure_id']);
            if ($current['status'] === ReservationStatus::Confirmed->value) {
                $statement = $this->connection->prepare(
                    'UPDATE reservations SET status = :status WHERE id = :id AND status = :current_status'
                );
                $statement->execute([
                    'status' => ReservationStatus::Cancelled->value,
                    'current_status' => ReservationStatus::Confirmed->value,
                    'id' => $id,
                ]);
                $this->audit?->record(
                    $actorId,
                    'cancelled',
                    'reservation',
                    $id,
                    ['status' => ReservationStatus::Confirmed->value],
                    ['status' => ReservationStatus::Cancelled->value]
                );
            }
            $this->connection->commit();

            return $this->findById($id) ?? throw new NotFoundException('Reserva no encontrada.');
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function list(): array
    {
        $ids = $this->connection->query('SELECT id FROM reservations ORDER BY created_at DESC, id DESC')
            ->fetchAll(PDO::FETCH_COLUMN);

        return array_map(
            fn ($id): Reservation => $this->findById((int) $id)
                ?? throw new NotFoundException('Reserva no encontrada.'),
            $ids
        );
    }

    public function findById(int $id): ?Reservation
    {
        $statement = $this->connection->prepare(self::selectQuery() . ' WHERE r.id = :id');
        $statement->execute(['id' => $id]);
        $record = $statement->fetch();
        if ($record === false) {
            return null;
        }
        $passengers = $this->connection->prepare(
            'SELECT full_name FROM reservation_passengers WHERE reservation_id = :id ORDER BY id'
        );
        $passengers->execute(['id' => $id]);

        return self::toReservation($record, $passengers->fetchAll(PDO::FETCH_COLUMN));
    }

    private function lockReservation(int $id): array
    {
        $statement = $this->connection->prepare('SELECT * FROM reservations WHERE id = :id FOR UPDATE');
        $statement->execute(['id' => $id]);
        $record = $statement->fetch();
        if ($record === false) {
            throw new NotFoundException('Reserva no encontrada.');
        }

        return $record;
    }

    private function lockDeparture(int $id): array
    {
        $statement = $this->connection->prepare(
            'SELECT d.id, d.capacity, d.status, t.is_active, t.base_price '
            . 'FROM departures d INNER JOIN tours t ON t.id = d.tour_id WHERE d.id = :id FOR UPDATE'
        );
        $statement->execute(['id' => $id]);
        $record = $statement->fetch();
        if ($record === false) {
            throw new NotFoundException('Salida no encontrada.');
        }

        return $record;
    }

    private function assertCapacity(array $departure, int $requested, ?int $excludeReservationId = null): void
    {
        $parameters = [
            'departure_id' => (int) $departure['id'],
            'status' => ReservationStatus::Confirmed->value,
        ];
        if ($excludeReservationId !== null) {
            $parameters['reservation_id'] = $excludeReservationId;
        }
        $statement = $this->connection->prepare(
            'SELECT id, passenger_count FROM reservations '
            . 'WHERE departure_id = :departure_id AND status = :status'
            . ($excludeReservationId === null ? '' : ' AND id <> :reservation_id')
            . ' FOR UPDATE'
        );
        $statement->execute($parameters);
        $occupied = array_sum(array_map(
            static fn (array $reservation): int => (int) $reservation['passenger_count'],
            $statement->fetchAll()
        ));
        if ($occupied + $requested > (int) $departure['capacity']) {
            throw new ConflictException('No hay suficientes cupos disponibles para esta salida.');
        }
    }

    private function assertTouristExists(int $id): void
    {
        $statement = $this->connection->prepare('SELECT id FROM tourists WHERE id = :id');
        $statement->execute(['id' => $id]);
        if ($statement->fetchColumn() === false) {
            throw new NotFoundException('Turista no encontrado.');
        }
    }

    private function findByIdempotencyKey(int $actorId, string $key): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, request_hash FROM reservations WHERE created_by = :actor_id AND idempotency_key = :key'
        );
        $statement->execute(['actor_id' => $actorId, 'key' => $key]);
        $record = $statement->fetch();

        return $record === false ? null : $record;
    }

    private function insertPassengers(int $reservationId, array $names): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO reservation_passengers (reservation_id, full_name) VALUES (:reservation_id, :full_name)'
        );
        foreach ($names as $name) {
            $statement->execute(['reservation_id' => $reservationId, 'full_name' => $name]);
        }
    }

    private function replacePassengers(int $reservationId, array $names): void
    {
        $statement = $this->connection->prepare('DELETE FROM reservation_passengers WHERE reservation_id = :id');
        $statement->execute(['id' => $reservationId]);
        $this->insertPassengers($reservationId, $names);
    }

    private static function assertBookable(array $departure): void
    {
        if ((int) $departure['is_active'] !== 1) {
            throw new ConflictException('Solo se puede reservar en salidas de tours activos.');
        }
        if ($departure['status'] !== 'programada') {
            throw new ConflictException('Solo se puede reservar en salidas programadas.');
        }
    }

    private static function assertSameRequest(string $storedHash, string $payloadHash): void
    {
        if (!hash_equals($storedHash, $payloadHash)) {
            throw new ConflictException('La clave de idempotencia ya se usó con otros datos.');
        }
    }

    private static function totalCents(int $priceCents, int $passengerCount): int
    {
        if ($priceCents < 0 || $passengerCount < 1 || $priceCents > intdiv(self::MAX_TOTAL_CENTS, $passengerCount)) {
            throw new ValidationException('El total de la reserva supera el monto permitido.', 'passengers');
        }

        return $priceCents * $passengerCount;
    }

    private static function parseCents(string $value): int
    {
        if (!preg_match('/\A([0-9]+)\.([0-9]{2})\z/', $value, $parts)) {
            throw new \RuntimeException('Stored reservation price has an invalid decimal format.');
        }

        return ((int) $parts[1] * 100) + (int) $parts[2];
    }

    private static function formatCents(int $cents): string
    {
        return intdiv($cents, 100) . '.'
            . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private static function selectQuery(): string
    {
        return 'SELECT r.id, r.departure_id, t.name AS tour_name, r.tourist_id, '
            . 'tr.full_name AS tourist_name, r.passenger_count, r.price_per_person, r.total_price, p.paid_net, '
            . 'r.status, r.created_at, r.updated_at, d.start_at AS departure_start_at '
            . 'FROM reservations r '
            . 'INNER JOIN departures d ON d.id = r.departure_id '
            . 'INNER JOIN tours t ON t.id = d.tour_id '
            . 'INNER JOIN tourists tr ON tr.id = r.tourist_id '
            . ' LEFT JOIN (SELECT reservation_id, COALESCE(SUM(amount), 0) AS paid_net '
            . "FROM payments WHERE status = 'aplicado' GROUP BY reservation_id) p "
            . ' ON p.reservation_id = r.id';
    }

    private static function toReservation(array $record, array $passengers): Reservation
    {
        return new Reservation(
            (int) $record['id'],
            (int) $record['departure_id'],
            (string) $record['tour_name'],
            (string) $record['departure_start_at'],
            (int) $record['tourist_id'],
            (string) $record['tourist_name'],
            (int) $record['passenger_count'],
            array_map(strval(...), $passengers),
            (string) $record['price_per_person'],
            (string) $record['total_price'],
            (string) ($record['paid_net'] ?? '0.00'),
            self::formatCents(
                self::parseCents((string) $record['total_price'])
                - self::parseCents((string) ($record['paid_net'] ?? '0.00'))
            ),
            ReservationStatus::from((string) $record['status']),
            (string) $record['created_at'],
            (string) $record['updated_at']
        );
    }
}
