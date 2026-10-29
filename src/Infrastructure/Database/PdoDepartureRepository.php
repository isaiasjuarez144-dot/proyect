<?php

declare(strict_types=1);

namespace UyuniTour\Infrastructure\Database;

use PDO;
use UyuniTour\Domain\Operations\Departure;
use UyuniTour\Domain\Operations\DepartureRepository;
use UyuniTour\Domain\Operations\DepartureStatus;
use UyuniTour\Domain\Shared\NotFoundException;
use UyuniTour\Domain\Shared\ValidationException;
use UyuniTour\Domain\Shared\ConflictException;

final class PdoDepartureRepository implements DepartureRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function create(
        int $tourId,
        string $startAt,
        string $endAt,
        int $capacity
    ): Departure {
        $statement = $this->connection->prepare(
            'INSERT INTO departures (tour_id, start_at, end_at, capacity) '
            . 'VALUES (:tour_id, :start_at, :end_at, :capacity)'
        );
        $statement->execute([
            'tour_id' => $tourId,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'capacity' => $capacity,
        ]);

        return $this->findById((int) $this->connection->lastInsertId())
            ?? throw new \RuntimeException('Created departure could not be reloaded.');
    }

    public function list(
        ?int $tourId,
        ?string $from,
        ?string $to,
        ?DepartureStatus $status
    ): array {
        $conditions = [];
        $parameters = [];
        if ($tourId !== null) {
            $conditions[] = 'd.tour_id = :tour_id';
            $parameters['tour_id'] = $tourId;
        }
        if ($from !== null) {
            $conditions[] = 'd.start_at >= :from_date';
            $parameters['from_date'] = $from . ' 00:00:00';
        }
        if ($to !== null) {
            $conditions[] = 'd.start_at <= :to_date';
            $parameters['to_date'] = $to . ' 23:59:59';
        }
        if ($status !== null) {
            $conditions[] = 'd.status = :status';
            $parameters['status'] = $status->value;
        }
        $sql = self::selectQuery();
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY d.start_at, d.id';
        $statement = $this->connection->prepare($sql);
        $statement->execute($parameters);

        return array_map(self::toDeparture(...), $statement->fetchAll());
    }

    public function findById(int $id): ?Departure
    {
        $statement = $this->connection->prepare(self::selectQuery() . ' WHERE d.id = :id');
        $statement->execute(['id' => $id]);
        $record = $statement->fetch();

        return $record === false ? null : self::toDeparture($record);
    }

    public function transitionStatus(
        int $id,
        DepartureStatus $current,
        DepartureStatus $next
    ): Departure {
        $this->beginConsistentTransaction();
        try {
            $lock = $this->connection->prepare(
                'SELECT id, start_at, end_at, capacity, status FROM departures WHERE id = :id FOR UPDATE'
            );
            $lock->execute(['id' => $id]);
            $departure = $lock->fetch();
            if ($departure === false) {
                throw new NotFoundException('Salida no encontrada.');
            }
            if ($departure['status'] !== $current->value) {
                throw new ValidationException('La salida cambió; vuelve a cargarla antes de cambiar el estado.');
            }
            if ($next === DepartureStatus::InProgress) {
                $this->assertDepartureCanStart($departure);
            }
            $statement = $this->connection->prepare(
                'UPDATE departures SET status = :next_status WHERE id = :id AND status = :current_status'
            );
            $statement->execute([
                'next_status' => $next->value,
                'id' => $id,
                'current_status' => $current->value,
            ]);
            $this->connection->commit();

            return $this->findById($id) ?? throw new NotFoundException('Salida no encontrada.');
        } catch (\Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function updateCapacity(int $id, int $capacity): Departure
    {
        $this->beginConsistentTransaction();
        try {
            $lock = $this->connection->prepare('SELECT id FROM departures WHERE id = :id FOR UPDATE');
            $lock->execute(['id' => $id]);
            if ($lock->fetchColumn() === false) {
                throw new NotFoundException('Salida no encontrada.');
            }
            $occupied = $this->connection->prepare(
                'SELECT COALESCE(SUM(passenger_count), 0) FROM reservations '
                . 'WHERE departure_id = :id AND status = :status'
            );
            $occupied->execute(['id' => $id, 'status' => 'confirmada']);
            if ($capacity < (int) $occupied->fetchColumn()) {
                throw new ConflictException('La capacidad no puede ser menor que las plazas reservadas.');
            }
            $assignedVehicle = $this->connection->prepare(
                'SELECT v.capacity FROM departure_assignments a '
                . 'INNER JOIN vehicles v ON v.id = a.vehicle_id '
                . 'WHERE a.departure_id = :id FOR UPDATE'
            );
            $assignedVehicle->execute(['id' => $id]);
            $vehicleCapacity = $assignedVehicle->fetchColumn();
            if ($vehicleCapacity !== false && $capacity > (int) $vehicleCapacity) {
                throw new ConflictException('La capacidad de la salida no puede superar la del vehículo.');
            }
            $update = $this->connection->prepare('UPDATE departures SET capacity = :capacity WHERE id = :id');
            $update->execute(['capacity' => $capacity, 'id' => $id]);
            $this->connection->commit();

            return $this->findById($id) ?? throw new NotFoundException('Salida no encontrada.');
        } catch (\Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function updateSchedule(int $id, string $startAt, string $endAt): Departure
    {
        $this->beginConsistentTransaction();
        try {
            $lock = $this->connection->prepare(
                'SELECT id, start_at, end_at, capacity, status FROM departures WHERE id = :id FOR UPDATE'
            );
            $lock->execute(['id' => $id]);
            $departure = $lock->fetch();
            if ($departure === false) {
                throw new NotFoundException('Salida no encontrada.');
            }
            if ($departure['status'] !== DepartureStatus::Scheduled->value) {
                throw new ConflictException('Solo se puede cambiar el horario de una salida programada.');
            }
            $assignment = $this->connection->prepare(
                'SELECT vehicle_id, driver_user_id FROM departure_assignments '
                . 'WHERE departure_id = :id'
            );
            $assignment->execute(['id' => $id]);
            $assigned = $assignment->fetch();
            if ($assigned !== false) {
                $vehicleLock = $this->connection->prepare(
                    'SELECT id, capacity, status FROM vehicles WHERE id = :id FOR UPDATE'
                );
                $vehicleLock->execute(['id' => $assigned['vehicle_id']]);
                $vehicle = $vehicleLock->fetch();
                $driverLock = $this->connection->prepare(
                    'SELECT id, role, is_active FROM users WHERE id = :id FOR UPDATE'
                );
                $driverLock->execute(['id' => $assigned['driver_user_id']]);
                $driver = $driverLock->fetch();
                if (
                    $vehicle === false
                    || $driver === false
                    || $vehicle['status'] !== 'operativo'
                    || $driver['role'] !== 'driver_guide'
                    || (int) $driver['is_active'] !== 1
                ) {
                    throw new ConflictException('El vehículo o conductor asignado ya no está disponible.');
                }
                if ((int) $departure['capacity'] > (int) $vehicle['capacity']) {
                    throw new ConflictException('La capacidad de la salida supera la del vehículo.');
                }
                $maintenance = $this->connection->prepare(
                    'SELECT id FROM vehicle_maintenance WHERE vehicle_id = :vehicle_id '
                    . 'AND status IN (\'programado\', \'en_curso\') '
                    . 'AND start_at < :end_at AND end_at > :start_at LIMIT 1'
                );
                $maintenance->execute([
                    'vehicle_id' => $assigned['vehicle_id'],
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                ]);
                if ($maintenance->fetchColumn() !== false) {
                    throw new ConflictException('El horario coincide con mantenimiento del vehículo.');
                }
                $overlap = $this->connection->prepare(
                    'SELECT a.departure_id FROM departure_assignments a '
                    . 'INNER JOIN departures d ON d.id = a.departure_id '
                    . 'WHERE a.departure_id <> :id AND d.status <> \'cancelada\' '
                    . 'AND d.start_at < :end_at AND d.end_at > :start_at '
                    . 'AND (a.vehicle_id = :vehicle_id OR a.driver_user_id = :driver_id) LIMIT 1'
                );
                $overlap->execute([
                    'id' => $id,
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                    'vehicle_id' => $assigned['vehicle_id'],
                    'driver_id' => $assigned['driver_user_id'],
                ]);
                if ($overlap->fetchColumn() !== false) {
                    throw new ConflictException('El nuevo horario se cruza con otra asignación.');
                }
            }
            $update = $this->connection->prepare(
                'UPDATE departures SET start_at = :start_at, end_at = :end_at WHERE id = :id'
            );
            $update->execute(['start_at' => $startAt, 'end_at' => $endAt, 'id' => $id]);
            $this->connection->commit();

            return $this->findById($id) ?? throw new NotFoundException('Salida no encontrada.');
        } catch (\Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    private function assertDepartureCanStart(array $departure): void
    {
        $assignment = $this->connection->prepare(
            'SELECT vehicle_id, driver_user_id FROM departure_assignments '
            . 'WHERE departure_id = :id'
        );
        $assignment->execute(['id' => $departure['id']]);
        $assigned = $assignment->fetch();
        if ($assigned === false) {
            throw new ConflictException('Asigna un vehículo y un conductor antes de iniciar la salida.');
        }

        $vehicleLock = $this->connection->prepare(
            'SELECT id, capacity, status FROM vehicles WHERE id = :id FOR UPDATE'
        );
        $vehicleLock->execute(['id' => $assigned['vehicle_id']]);
        $vehicle = $vehicleLock->fetch();
        $driverLock = $this->connection->prepare(
            'SELECT id, role, is_active FROM users WHERE id = :id FOR UPDATE'
        );
        $driverLock->execute(['id' => $assigned['driver_user_id']]);
        $driver = $driverLock->fetch();
        if (
            $vehicle === false
            || $driver === false
            || $vehicle['status'] !== 'operativo'
            || $driver['role'] !== 'driver_guide'
            || (int) $driver['is_active'] !== 1
        ) {
            throw new ConflictException('El vehículo o conductor asignado ya no está disponible.');
        }
        if ((int) $departure['capacity'] > (int) $vehicle['capacity']) {
            throw new ConflictException('La capacidad de la salida supera la del vehículo.');
        }
        $maintenance = $this->connection->prepare(
            'SELECT id FROM vehicle_maintenance WHERE vehicle_id = :vehicle_id '
            . 'AND status IN (\'programado\', \'en_curso\') '
            . 'AND start_at < :end_at AND end_at > :start_at LIMIT 1'
        );
        $maintenance->execute([
            'vehicle_id' => $assigned['vehicle_id'],
            'start_at' => $departure['start_at'],
            'end_at' => $departure['end_at'],
        ]);
        if ($maintenance->fetchColumn() !== false) {
            throw new ConflictException('El vehículo tiene mantenimiento durante la salida.');
        }
    }

    private function beginConsistentTransaction(): void
    {
        $this->connection->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $this->connection->beginTransaction();
    }

    private static function selectQuery(): string
    {
        return 'SELECT d.id, d.tour_id, t.name AS tour_name, d.start_at, d.end_at, d.capacity, '
            . '(SELECT COALESCE(SUM(r.passenger_count), 0) FROM reservations r '
            . 'WHERE r.departure_id = d.id AND r.status = \'confirmada\') AS occupied_seats, '
            . 'd.status, d.created_at, d.updated_at FROM departures d '
            . 'INNER JOIN tours t ON t.id = d.tour_id';
    }

    private static function toDeparture(array $record): Departure
    {
        return new Departure(
            (int) $record['id'],
            (int) $record['tour_id'],
            (string) $record['tour_name'],
            (string) $record['start_at'],
            (string) $record['end_at'],
            (int) $record['capacity'],
            (int) $record['occupied_seats'],
            (int) $record['capacity'] - (int) $record['occupied_seats'],
            DepartureStatus::from((string) $record['status']),
            (string) $record['created_at'],
            (string) $record['updated_at']
        );
    }
}
