<?php

declare(strict_types=1);

namespace UyuniTour\Application\Operations;

use DateTimeImmutable;
use UyuniTour\Domain\Identity\Role;
use UyuniTour\Domain\Identity\User;
use UyuniTour\Domain\Operations\FleetRepository;
use UyuniTour\Domain\Shared\AuthorizationException;
use UyuniTour\Domain\Shared\ValidationException;

final class FleetService
{
    public function __construct(private readonly FleetRepository $fleet)
    {
    }

    public function listVehicles(User $actor): array
    {
        self::assertCanManage($actor);

        return $this->fleet->listVehicles();
    }

    public function createVehicle(User $actor, array $input): array
    {
        self::assertCanManage($actor);
        self::rejectUnknown($input, ['plate', 'capacity']);
        $plate = self::plate($input['plate'] ?? null);
        $capacity = self::positiveInteger($input['capacity'] ?? null, 'capacity');

        return $this->fleet->createVehicle($plate, $capacity, $actor->id);
    }

    public function updateVehicle(User $actor, int $id, array $input): array
    {
        self::assertCanManage($actor);
        self::rejectUnknown($input, ['plate', 'capacity', 'status']);
        if ($input === []) {
            throw new ValidationException('Indica al menos un cambio.');
        }
        $changes = [];
        if (array_key_exists('plate', $input)) {
            $changes['plate'] = self::plate($input['plate']);
        }
        if (array_key_exists('capacity', $input)) {
            $changes['capacity'] = self::positiveInteger($input['capacity'], 'capacity');
        }
        if (array_key_exists('status', $input)) {
            if (!is_string($input['status']) || !in_array($input['status'], ['operativo', 'inactivo'], true)) {
                throw new ValidationException('El estado del vehículo no es válido.', 'status');
            }
            $changes['status'] = $input['status'];
        }

        return $this->fleet->updateVehicle($id, $changes, $actor->id);
    }

    public function listDrivers(User $actor): array
    {
        self::assertCanManage($actor);

        return $this->fleet->listDrivers();
    }

    public function listAssignments(User $actor): array
    {
        self::assertCanManage($actor);

        return $this->fleet->listAssignments();
    }

    public function assign(User $actor, int $departureId, array $input): array
    {
        self::assertCanManage($actor);
        self::rejectUnknown($input, ['vehicle_id', 'driver_user_id']);

        return $this->fleet->assign(
            $departureId,
            self::positiveInteger($input['vehicle_id'] ?? null, 'vehicle_id'),
            self::positiveInteger($input['driver_user_id'] ?? null, 'driver_user_id'),
            $actor->id
        );
    }

    public function listMaintenance(User $actor, array $filters): array
    {
        self::assertCanManage($actor);
        self::rejectUnknown($filters, ['vehicle_id']);
        $vehicleId = null;
        if (isset($filters['vehicle_id'])) {
            $vehicleId = self::positiveInteger($filters['vehicle_id'], 'vehicle_id');
        }

        return $this->fleet->listMaintenance($vehicleId);
    }

    public function createMaintenance(User $actor, int $vehicleId, array $input): array
    {
        self::assertCanManage($actor);
        self::rejectUnknown($input, ['start_at', 'end_at', 'description']);
        $startAt = self::dateTime($input['start_at'] ?? null, 'start_at');
        $endAt = self::dateTime($input['end_at'] ?? null, 'end_at');
        if ($endAt <= $startAt) {
            throw new ValidationException('El fin debe ser posterior al inicio.', 'end_at');
        }
        $description = self::description($input['description'] ?? null, 'description', 1000);

        return $this->fleet->createMaintenance($vehicleId, $startAt, $endAt, $description, $actor->id);
    }

    public function transitionMaintenance(User $actor, int $id, array $input): array
    {
        self::assertCanManage($actor);
        self::rejectUnknown($input, ['status']);
        $status = $input['status'] ?? null;
        if (!is_string($status) || !in_array($status, ['en_curso', 'completado', 'cancelado'], true)) {
            throw new ValidationException('El estado del mantenimiento no es válido.', 'status');
        }

        return $this->fleet->transitionMaintenance($id, $status, $actor->id);
    }

    public function createIncident(User $actor, int $departureId, array $input): array
    {
        self::assertCanReportIncident($actor);
        self::rejectUnknown($input, ['occurred_at', 'description']);
        $occurredAt = self::dateTime($input['occurred_at'] ?? null, 'occurred_at');
        $description = self::description($input['description'] ?? null, 'description', 2000);

        return $this->fleet->createIncident($departureId, $actor->id, $occurredAt, $description);
    }

    public function listIncidents(User $actor, array $filters): array
    {
        self::rejectUnknown($filters, ['departure_id']);
        $departureId = null;
        if (isset($filters['departure_id'])) {
            $departureId = self::positiveInteger($filters['departure_id'], 'departure_id');
        }
        $driverId = null;
        if ($actor->role === Role::DriverGuide) {
            $driverId = $actor->id;
        } elseif (!in_array($actor->role, [Role::Administrator, Role::Operator], true)) {
            throw new AuthorizationException('Permiso insuficiente.');
        }

        return $this->fleet->listIncidents($departureId, $driverId);
    }

    public function listDriverDepartures(User $actor): array
    {
        if ($actor->role !== Role::DriverGuide) {
            throw new AuthorizationException('Permiso insuficiente.');
        }

        return $this->fleet->listDriverDepartures($actor->id);
    }

    private static function plate(mixed $value): string
    {
        if (!is_string($value)) {
            throw new ValidationException('La placa es obligatoria.', 'plate');
        }
        $plate = strtoupper(trim($value));
        if ($plate === '' || mb_strlen($plate) > 20) {
            throw new ValidationException('La placa debe tener entre 1 y 20 caracteres.', 'plate');
        }

        return $plate;
    }

    private static function description(mixed $value, string $field, int $maxLength): string
    {
        if (!is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > $maxLength) {
            throw new ValidationException(
                'Escribe una descripción de hasta ' . $maxLength . ' caracteres.',
                $field
            );
        }

        return trim($value);
    }

    private static function dateTime(mixed $value, string $field): string
    {
        if (
            !is_string($value)
            || !preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}\z/', $value)
            || (int) substr($value, 0, 4) < 1000
        ) {
            throw new ValidationException(
                'La fecha y hora debe existir y estar entre los años 1000 y 9999.',
                $field
            );
        }
        $dateTime = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (
            $dateTime === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $dateTime->format('Y-m-d\TH:i') !== $value
        ) {
            throw new ValidationException(
                'La fecha y hora debe existir y estar entre los años 1000 y 9999.',
                $field
            );
        }

        return $dateTime->format('Y-m-d H:i:s');
    }

    private static function positiveInteger(mixed $value, string $field): int
    {
        if (!is_int($value) || $value < 1 || $value > 4294967295) {
            throw new ValidationException('Debe ser un entero positivo válido.', $field);
        }

        return $value;
    }

    private static function rejectUnknown(array $input, array $allowed): void
    {
        foreach (array_keys($input) as $field) {
            if (!in_array($field, $allowed, true)) {
                throw new ValidationException('El campo ' . $field . ' no está permitido.');
            }
        }
    }

    private static function assertCanManage(User $actor): void
    {
        if (!in_array($actor->role, [Role::Administrator, Role::Operator], true)) {
            throw new AuthorizationException('Permiso insuficiente.');
        }
    }

    private static function assertCanReportIncident(User $actor): void
    {
        if (!in_array($actor->role, [Role::Administrator, Role::Operator, Role::DriverGuide], true)) {
            throw new AuthorizationException('Permiso insuficiente.');
        }
    }
}
