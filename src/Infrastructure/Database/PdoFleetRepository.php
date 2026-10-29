<?php

declare(strict_types=1);

namespace UyuniTour\Infrastructure\Database;

use PDO;
use Throwable;
use UyuniTour\Domain\Operations\FleetRepository;
use UyuniTour\Domain\Audit\AuditRepository;
use UyuniTour\Domain\Shared\AuthorizationException;
use UyuniTour\Domain\Shared\ConflictException;
use UyuniTour\Domain\Shared\NotFoundException;

final class PdoFleetRepository implements FleetRepository
{
    public function __construct(
        private readonly PDO $connection,
        private readonly ?AuditRepository $audit = null
    ) {
    }

    public function createVehicle(string $plate, int $capacity, int $actorId): array
    {
        $this->beginConsistentTransaction();
        try {
            $statement = $this->connection->prepare(
                'INSERT INTO vehicles (plate, capacity) VALUES (:plate, :capacity)'
            );
            $statement->execute(['plate' => $plate, 'capacity' => $capacity]);
            $id = (int) $this->connection->lastInsertId();
            $vehicle = $this->findVehicle($id);
            $this->audit?->record($actorId, 'created', 'vehicle', $id, null, $vehicle);
            $this->connection->commit();
            return $vehicle;
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    public function listVehicles(): array
    {
        $records = $this->connection->query(
            'SELECT id, plate, capacity, status, created_at, updated_at FROM vehicles ORDER BY plate, id'
        )->fetchAll();

        return array_map(self::vehicle(...), $records);
    }

    public function updateVehicle(int $id, array $changes, int $actorId): array
    {
        $this->beginConsistentTransaction();
        try {
            $vehicle = $this->lockVehicle($id);
            if (isset($changes['capacity'])) {
                $conflict = $this->connection->prepare(
                    'SELECT d.id FROM departure_assignments a '
                    . 'INNER JOIN departures d ON d.id = a.departure_id '
                    . 'WHERE a.vehicle_id = :vehicle_id AND d.status <> \'cancelada\' '
                    . 'AND d.capacity > :capacity LIMIT 1'
                );
                $conflict->execute(['vehicle_id' => $id, 'capacity' => $changes['capacity']]);
                if ($conflict->fetchColumn() !== false) {
                    throw new ConflictException(
                        'La capacidad del vehículo no puede ser menor que la de una salida asignada.'
                    );
                }
            }
            if (($changes['status'] ?? null) === 'inactivo') {
                $activeDeparture = $this->connection->prepare(
                    'SELECT d.id FROM departure_assignments a '
                    . 'INNER JOIN departures d ON d.id = a.departure_id '
                    . 'WHERE a.vehicle_id = :vehicle_id AND d.status = \'en_curso\' LIMIT 1'
                );
                $activeDeparture->execute(['vehicle_id' => $id]);
                if ($activeDeparture->fetchColumn() !== false) {
                    throw new ConflictException('No se puede desactivar un vehículo asignado a una salida en curso.');
                }
            }
            $fields = [];
            $parameters = ['id' => $id];
            foreach (['plate', 'capacity', 'status'] as $field) {
                if (array_key_exists($field, $changes)) {
                    $fields[] = $field . ' = :' . $field;
                    $parameters[$field] = $changes[$field];
                }
            }
            if ($fields !== []) {
                $update = $this->connection->prepare(
                    'UPDATE vehicles SET ' . implode(', ', $fields) . ' WHERE id = :id'
                );
                $update->execute($parameters);
            }
            $updated = $this->findVehicle($id);
            $this->audit?->record($actorId, 'updated', 'vehicle', $id, $vehicle, $updated);
            $this->connection->commit();
            return $updated;
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    public function listDrivers(): array
    {
        return $this->connection->query(
            "SELECT id, email FROM users WHERE role = 'driver_guide' AND is_active = 1 ORDER BY email, id"
        )->fetchAll();
    }

    public function listAssignments(): array
    {
        $records = $this->connection->query(self::assignmentQuery() . ' ORDER BY d.start_at, d.id')
            ->fetchAll();

        return array_map(fn (array $record): array => $this->withAssignmentHistory(
            self::assignment($record)
        ), $records);
    }

    public function assign(int $departureId, int $vehicleId, int $driverId, int $actorId): array
    {
        $this->beginConsistentTransaction();
        try {
            $departure = $this->lockDeparture($departureId);
            if ($departure['status'] !== 'programada') {
                throw new ConflictException('Solo se pueden asignar recursos a una salida programada.');
            }
            $existing = $this->connection->prepare(
                'SELECT vehicle_id, driver_user_id FROM departure_assignments '
                . 'WHERE departure_id = :departure_id'
            );
            $existing->execute(['departure_id' => $departureId]);
            $current = $existing->fetch();
            $vehicleIds = array_unique([$vehicleId]);
            $driverIds = array_unique([$driverId]);
            if ($current !== false) {
                $vehicleIds[] = (int) $current['vehicle_id'];
                $driverIds[] = (int) $current['driver_user_id'];
            }
            sort($vehicleIds, SORT_NUMERIC);
            sort($driverIds, SORT_NUMERIC);
            $lockedVehicles = [];
            foreach ($vehicleIds as $id) {
                $lockedVehicles[$id] = $this->lockVehicle($id);
            }
            $lockedDrivers = [];
            foreach ($driverIds as $id) {
                $lockedDrivers[$id] = $this->lockDriver($id);
            }

            $vehicle = $lockedVehicles[$vehicleId];
            $driver = $lockedDrivers[$driverId];
            self::assertResourcesOperational($vehicle, $driver);
            if ((int) $departure['capacity'] > (int) $vehicle['capacity']) {
                throw new ConflictException('La capacidad de la salida supera la del vehículo.');
            }
            $this->assertNoMaintenanceConflict(
                $vehicleId,
                (string) $departure['start_at'],
                (string) $departure['end_at']
            );
            $this->assertNoAssignmentConflict(
                $departureId,
                $vehicleId,
                $driverId,
                (string) $departure['start_at'],
                (string) $departure['end_at']
            );
            if ($current === false) {
                $save = $this->connection->prepare(
                    'INSERT INTO departure_assignments '
                    . '(departure_id, vehicle_id, driver_user_id, assigned_by) '
                    . 'VALUES (:departure_id, :vehicle_id, :driver_user_id, :assigned_by)'
                );
                $save->execute([
                    'departure_id' => $departureId,
                    'vehicle_id' => $vehicleId,
                    'driver_user_id' => $driverId,
                    'assigned_by' => $actorId,
                ]);
            } elseif (
                (int) $current['vehicle_id'] !== $vehicleId
                || (int) $current['driver_user_id'] !== $driverId
            ) {
                $archive = $this->connection->prepare(
                    'INSERT INTO departure_assignment_history '
                    . '(departure_id, vehicle_id, driver_user_id, assigned_by, replaced_by, assigned_at) '
                    . 'SELECT departure_id, vehicle_id, driver_user_id, assigned_by, :replaced_by, updated_at '
                    . 'FROM departure_assignments WHERE departure_id = :departure_id'
                );
                $archive->execute(['replaced_by' => $actorId, 'departure_id' => $departureId]);
                $save = $this->connection->prepare(
                    'UPDATE departure_assignments SET vehicle_id = :vehicle_id, '
                    . 'driver_user_id = :driver_user_id, assigned_by = :assigned_by '
                    . 'WHERE departure_id = :departure_id'
                );
                $save->execute([
                    'departure_id' => $departureId,
                    'vehicle_id' => $vehicleId,
                    'driver_user_id' => $driverId,
                    'assigned_by' => $actorId,
                ]);
            }
            $this->audit?->record(
                $actorId,
                'updated',
                'assignment',
                $departureId,
                $current === false ? null : [
                    'vehicle_id' => (int) $current['vehicle_id'],
                    'driver_user_id' => (int) $current['driver_user_id'],
                ],
                ['vehicle_id' => $vehicleId, 'driver_user_id' => $driverId]
            );
            $this->connection->commit();

            return $this->findAssignment($departureId);
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    public function createMaintenance(
        int $vehicleId,
        string $startAt,
        string $endAt,
        string $description,
        int $actorId
    ): array {
        $this->beginConsistentTransaction();
        try {
            $this->lockVehicle($vehicleId);
            $this->assertNoAssignedDepartureConflict($vehicleId, $startAt, $endAt);
            $statement = $this->connection->prepare(
                'INSERT INTO vehicle_maintenance '
                . '(vehicle_id, start_at, end_at, description, created_by) '
                . 'VALUES (:vehicle_id, :start_at, :end_at, :description, :created_by)'
            );
            $statement->execute([
                'vehicle_id' => $vehicleId,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'description' => $description,
                'created_by' => $actorId,
            ]);
            $id = (int) $this->connection->lastInsertId();
            $this->audit?->record(
                $actorId,
                'created',
                'maintenance',
                $id,
                null,
                ['vehicle_id' => $vehicleId, 'start_at' => $startAt, 'end_at' => $endAt]
            );
            $this->connection->commit();

            return $this->findMaintenance($id);
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    public function listMaintenance(?int $vehicleId): array
    {
        $sql = 'SELECT m.id, m.vehicle_id, v.plate, m.start_at, m.end_at, m.description, '
            . 'm.status, m.created_at, m.updated_at FROM vehicle_maintenance m '
            . 'INNER JOIN vehicles v ON v.id = m.vehicle_id';
        $parameters = [];
        if ($vehicleId !== null) {
            $sql .= ' WHERE m.vehicle_id = :vehicle_id';
            $parameters['vehicle_id'] = $vehicleId;
        }
        $sql .= ' ORDER BY m.start_at DESC, m.id DESC';
        $statement = $this->connection->prepare($sql);
        $statement->execute($parameters);

        return array_map(self::maintenance(...), $statement->fetchAll());
    }

    public function transitionMaintenance(int $id, string $nextStatus, int $actorId): array
    {
        $this->beginConsistentTransaction();
        try {
            $lock = $this->connection->prepare(
                'SELECT id, vehicle_id, status FROM vehicle_maintenance WHERE id = :id FOR UPDATE'
            );
            $lock->execute(['id' => $id]);
            $current = $lock->fetch();
            if ($current === false) {
                throw new NotFoundException('Mantenimiento no encontrado.');
            }
            $this->lockVehicle((int) $current['vehicle_id']);
            $valid = match ($current['status']) {
                'programado' => in_array($nextStatus, ['en_curso', 'cancelado'], true),
                'en_curso' => $nextStatus === 'completado',
                default => false,
            };
            if (!$valid) {
                throw new ConflictException('La transición de estado del mantenimiento no está permitida.');
            }
            $update = $this->connection->prepare(
                'UPDATE vehicle_maintenance SET status = :status WHERE id = :id'
            );
            $update->execute(['status' => $nextStatus, 'id' => $id]);
            $this->audit?->record(
                $actorId,
                'updated',
                'maintenance',
                $id,
                ['status' => $current['status']],
                ['status' => $nextStatus]
            );
            $this->connection->commit();

            return $this->findMaintenance($id);
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    public function createIncident(
        int $departureId,
        int $actorId,
        string $occurredAt,
        string $description
    ): array {
        $this->beginConsistentTransaction();
        try {
            $departure = $this->lockDeparture($departureId);
            if ($departure['status'] === 'cancelada') {
                throw new ConflictException('No se registran incidencias en salidas canceladas.');
            }
            $user = $this->connection->prepare(
                'SELECT role FROM users WHERE id = :id AND is_active = 1 FOR UPDATE'
            );
            $user->execute(['id' => $actorId]);
            $role = $user->fetchColumn();
            if (!in_array($role, ['administrator', 'operator', 'driver_guide'], true)) {
                throw new AuthorizationException('Permiso insuficiente.');
            }
            if ($role === 'driver_guide') {
                $assignment = $this->connection->prepare(
                    'SELECT driver_user_id FROM departure_assignments WHERE departure_id = :id'
                );
                $assignment->execute(['id' => $departureId]);
                if ((int) $assignment->fetchColumn() !== $actorId) {
                    throw new NotFoundException('Salida no encontrada.');
                }
            }
            $insert = $this->connection->prepare(
                'INSERT INTO departure_incidents (departure_id, reported_by, occurred_at, description) '
                . 'VALUES (:departure_id, :reported_by, :occurred_at, :description)'
            );
            $insert->execute([
                'departure_id' => $departureId,
                'reported_by' => $actorId,
                'occurred_at' => $occurredAt,
                'description' => $description,
            ]);
            $id = (int) $this->connection->lastInsertId();
            $this->connection->commit();

            return $this->findIncident($id);
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    public function listIncidents(?int $departureId, ?int $driverId): array
    {
        $conditions = [];
        $parameters = [];
        if ($departureId !== null) {
            $conditions[] = 'i.departure_id = :departure_id';
            $parameters['departure_id'] = $departureId;
        }
        if ($driverId !== null) {
            $conditions[] = 'a.driver_user_id = :driver_id';
            $parameters['driver_id'] = $driverId;
        }
        $sql = 'SELECT i.id, i.departure_id, t.name AS tour_name, i.occurred_at, '
            . 'i.description, u.email AS reported_by, i.created_at FROM departure_incidents i '
            . 'INNER JOIN departures d ON d.id = i.departure_id '
            . 'INNER JOIN tours t ON t.id = d.tour_id '
            . 'INNER JOIN users u ON u.id = i.reported_by';
        if ($driverId !== null) {
            $sql .= ' INNER JOIN departure_assignments a ON a.departure_id = d.id';
        }
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY i.occurred_at DESC, i.id DESC';
        $statement = $this->connection->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    public function listDriverDepartures(int $driverId): array
    {
        $statement = $this->connection->prepare(
            'SELECT d.id, d.start_at, d.end_at, d.status, d.capacity, t.name AS tour_name, '
            . 'v.plate AS vehicle_plate FROM departure_assignments a '
            . 'INNER JOIN departures d ON d.id = a.departure_id '
            . 'INNER JOIN tours t ON t.id = d.tour_id '
            . 'INNER JOIN vehicles v ON v.id = a.vehicle_id '
            . 'WHERE a.driver_user_id = :driver_id ORDER BY d.start_at, d.id'
        );
        $statement->execute(['driver_id' => $driverId]);
        $departures = $statement->fetchAll();
        $passengers = $this->connection->prepare(
            'SELECT p.full_name FROM reservations r '
            . 'INNER JOIN reservation_passengers p ON p.reservation_id = r.id '
            . 'WHERE r.departure_id = :departure_id AND r.status = \'confirmada\' '
            . 'ORDER BY r.id, p.id'
        );
        foreach ($departures as &$departure) {
            $passengers->execute(['departure_id' => $departure['id']]);
            $departure['passengers'] = $passengers->fetchAll(PDO::FETCH_COLUMN);
        }
        unset($departure);

        return $departures;
    }

    private function lockDeparture(int $id): array
    {
        $statement = $this->connection->prepare(
            'SELECT id, start_at, end_at, capacity, status FROM departures WHERE id = :id FOR UPDATE'
        );
        $statement->execute(['id' => $id]);
        $departure = $statement->fetch();
        if ($departure === false) {
            throw new NotFoundException('Salida no encontrada.');
        }

        return $departure;
    }

    private function lockVehicle(int $id): array
    {
        $statement = $this->connection->prepare(
            'SELECT id, plate, capacity, status FROM vehicles WHERE id = :id FOR UPDATE'
        );
        $statement->execute(['id' => $id]);
        $vehicle = $statement->fetch();
        if ($vehicle === false) {
            throw new NotFoundException('Vehículo no encontrado.');
        }

        return $vehicle;
    }

    private function lockDriver(int $id): array
    {
        $statement = $this->connection->prepare(
            'SELECT id, email, role, is_active FROM users WHERE id = :id FOR UPDATE'
        );
        $statement->execute(['id' => $id]);
        $driver = $statement->fetch();
        if ($driver === false) {
            throw new NotFoundException('Conductor no encontrado.');
        }

        return $driver;
    }

    private function assertNoMaintenanceConflict(int $vehicleId, string $startAt, string $endAt): void
    {
        $statement = $this->connection->prepare(
            'SELECT id FROM vehicle_maintenance WHERE vehicle_id = :vehicle_id '
            . 'AND status IN (\'programado\', \'en_curso\') '
            . 'AND start_at < :end_at AND end_at > :start_at LIMIT 1'
        );
        $statement->execute([
            'vehicle_id' => $vehicleId,
            'start_at' => $startAt,
            'end_at' => $endAt,
        ]);
        if ($statement->fetchColumn() !== false) {
            throw new ConflictException('El vehículo tiene mantenimiento en ese horario.');
        }
    }

    private function assertNoAssignmentConflict(
        int $departureId,
        int $vehicleId,
        int $driverId,
        string $startAt,
        string $endAt
    ): void {
        $statement = $this->connection->prepare(
            'SELECT a.departure_id FROM departure_assignments a '
            . 'INNER JOIN departures d ON d.id = a.departure_id '
            . 'WHERE a.departure_id <> :departure_id AND d.status <> \'cancelada\' '
            . 'AND d.start_at < :end_at AND d.end_at > :start_at '
            . 'AND (a.vehicle_id = :vehicle_id OR a.driver_user_id = :driver_id) LIMIT 1'
        );
        $statement->execute([
            'departure_id' => $departureId,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'vehicle_id' => $vehicleId,
            'driver_id' => $driverId,
        ]);
        if ($statement->fetchColumn() !== false) {
            throw new ConflictException('El vehículo o conductor ya está asignado en ese horario.');
        }
    }

    private function assertNoAssignedDepartureConflict(int $vehicleId, string $startAt, string $endAt): void
    {
        $statement = $this->connection->prepare(
            'SELECT d.id FROM departure_assignments a '
            . 'INNER JOIN departures d ON d.id = a.departure_id '
            . 'WHERE a.vehicle_id = :vehicle_id AND d.status <> \'cancelada\' '
            . 'AND d.start_at < :end_at AND d.end_at > :start_at LIMIT 1'
        );
        $statement->execute([
            'vehicle_id' => $vehicleId,
            'start_at' => $startAt,
            'end_at' => $endAt,
        ]);
        if ($statement->fetchColumn() !== false) {
            throw new ConflictException('El mantenimiento coincide con una salida asignada.');
        }
    }

    private static function assertResourcesOperational(array $vehicle, array $driver): void
    {
        if ($vehicle['status'] !== 'operativo') {
            throw new ConflictException('El vehículo no está operativo.');
        }
        if ($driver['role'] !== 'driver_guide' || (int) $driver['is_active'] !== 1) {
            throw new ConflictException('El conductor debe tener una cuenta activa con rol Conductor/Guía.');
        }
    }

    private function findVehicle(int $id): array
    {
        $statement = $this->connection->prepare(
            'SELECT id, plate, capacity, status, created_at, updated_at FROM vehicles WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
        $record = $statement->fetch();
        if ($record === false) {
            throw new NotFoundException('Vehículo no encontrado.');
        }

        return self::vehicle($record);
    }

    private function findAssignment(int $departureId): array
    {
        $statement = $this->connection->prepare(
            self::assignmentQuery() . ' WHERE d.id = :departure_id'
        );
        $statement->execute(['departure_id' => $departureId]);
        $record = $statement->fetch();
        if ($record === false) {
            throw new NotFoundException('Asignación no encontrada.');
        }

        return $this->withAssignmentHistory(self::assignment($record));
    }

    private function withAssignmentHistory(array $assignment): array
    {
        $history = $this->connection->prepare(
            'SELECT h.id, h.vehicle_id, v.plate, h.driver_user_id, u.email AS driver_email, '
            . 'h.assigned_by, a.email AS assigned_by_email, h.replaced_by, r.email AS replaced_by_email, '
            . 'h.assigned_at, h.replaced_at FROM departure_assignment_history h '
            . 'INNER JOIN vehicles v ON v.id = h.vehicle_id '
            . 'INNER JOIN users u ON u.id = h.driver_user_id '
            . 'INNER JOIN users a ON a.id = h.assigned_by '
            . 'INNER JOIN users r ON r.id = h.replaced_by '
            . 'WHERE h.departure_id = :departure_id ORDER BY h.replaced_at, h.id'
        );
        $history->execute(['departure_id' => $assignment['departure_id']]);
        $assignment['history'] = $history->fetchAll();

        return $assignment;
    }

    private function findMaintenance(int $id): array
    {
        $statement = $this->connection->prepare(
            'SELECT m.id, m.vehicle_id, v.plate, m.start_at, m.end_at, m.description, '
            . 'm.status, m.created_at, m.updated_at FROM vehicle_maintenance m '
            . 'INNER JOIN vehicles v ON v.id = m.vehicle_id WHERE m.id = :id'
        );
        $statement->execute(['id' => $id]);

        return self::maintenance($statement->fetch() ?: throw new NotFoundException('Mantenimiento no encontrado.'));
    }

    private function findIncident(int $id): array
    {
        $statement = $this->connection->prepare(
            'SELECT i.id, i.departure_id, t.name AS tour_name, i.occurred_at, '
            . 'i.description, u.email AS reported_by, i.created_at FROM departure_incidents i '
            . 'INNER JOIN departures d ON d.id = i.departure_id '
            . 'INNER JOIN tours t ON t.id = d.tour_id '
            . 'INNER JOIN users u ON u.id = i.reported_by WHERE i.id = :id'
        );
        $statement->execute(['id' => $id]);
        $record = $statement->fetch();
        if ($record === false) {
            throw new NotFoundException('Incidencia no encontrada.');
        }

        return $record;
    }

    private static function assignmentQuery(): string
    {
        return 'SELECT a.id, a.departure_id, t.name AS tour_name, d.start_at, d.end_at, '
            . 'd.capacity AS departure_capacity, d.status AS departure_status, '
            . 'a.vehicle_id, v.plate, v.capacity AS vehicle_capacity, v.status AS vehicle_status, '
            . 'a.driver_user_id, u.email AS driver_email '
            . 'FROM departure_assignments a INNER JOIN departures d ON d.id = a.departure_id '
            . 'INNER JOIN tours t ON t.id = d.tour_id '
            . 'INNER JOIN vehicles v ON v.id = a.vehicle_id '
            . 'INNER JOIN users u ON u.id = a.driver_user_id';
    }

    private static function vehicle(array $record): array
    {
        return [
            'id' => (int) $record['id'],
            'plate' => (string) $record['plate'],
            'capacity' => (int) $record['capacity'],
            'status' => (string) $record['status'],
            'created_at' => (string) $record['created_at'],
            'updated_at' => (string) $record['updated_at'],
        ];
    }

    private static function assignment(array $record): array
    {
        return [
            'id' => (int) $record['id'],
            'departure_id' => (int) $record['departure_id'],
            'tour_name' => (string) $record['tour_name'],
            'start_at' => (string) $record['start_at'],
            'end_at' => (string) $record['end_at'],
            'departure_capacity' => (int) $record['departure_capacity'],
            'departure_status' => (string) $record['departure_status'],
            'vehicle_id' => (int) $record['vehicle_id'],
            'plate' => (string) $record['plate'],
            'vehicle_capacity' => (int) $record['vehicle_capacity'],
            'vehicle_status' => (string) $record['vehicle_status'],
            'driver_user_id' => (int) $record['driver_user_id'],
            'driver_email' => (string) $record['driver_email'],
        ];
    }

    private static function maintenance(array $record): array
    {
        return [
            'id' => (int) $record['id'],
            'vehicle_id' => (int) $record['vehicle_id'],
            'plate' => (string) $record['plate'],
            'start_at' => (string) $record['start_at'],
            'end_at' => (string) $record['end_at'],
            'description' => (string) $record['description'],
            'status' => (string) $record['status'],
            'created_at' => (string) $record['created_at'],
            'updated_at' => (string) $record['updated_at'],
        ];
    }

    private function rollback(): void
    {
        if ($this->connection->inTransaction()) {
            $this->connection->rollBack();
        }
    }

    private function beginConsistentTransaction(): void
    {
        $this->connection->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $this->connection->beginTransaction();
    }
}
