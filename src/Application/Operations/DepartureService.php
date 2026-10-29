<?php

declare(strict_types=1);

namespace UyuniTour\Application\Operations;

use DateTimeImmutable;
use UyuniTour\Domain\Catalog\TourRepository;
use UyuniTour\Domain\Identity\Role;
use UyuniTour\Domain\Identity\User;
use UyuniTour\Domain\Operations\Departure;
use UyuniTour\Domain\Operations\DepartureRepository;
use UyuniTour\Domain\Operations\DepartureStatus;
use UyuniTour\Domain\Shared\AuthorizationException;
use UyuniTour\Domain\Shared\NotFoundException;
use UyuniTour\Domain\Shared\ValidationException;

final class DepartureService
{
    public function __construct(
        private readonly DepartureRepository $departures,
        private readonly TourRepository $tours
    ) {
    }

    public function list(User $actor, array $filters): array
    {
        self::assertCanManage($actor);
        $tourId = null;
        if (isset($filters['tour_id'])) {
            $tourId = filter_var($filters['tour_id'], FILTER_VALIDATE_INT);
            if ($tourId === false || $tourId < 1) {
                throw new ValidationException('El filtro tour_id debe ser un identificador válido.');
            }
        }
        $from = isset($filters['from']) ? self::dateFilter($filters['from'], 'from') : null;
        $to = isset($filters['to']) ? self::dateFilter($filters['to'], 'to') : null;
        if ($from !== null && $to !== null && $from > $to) {
            throw new ValidationException('Desde no puede ser posterior a Hasta.', 'from');
        }
        $status = null;
        if (isset($filters['status'])) {
            if (!is_string($filters['status'])) {
                throw new ValidationException('El filtro status no es válido.');
            }
            $status = DepartureStatus::tryFrom($filters['status'])
                ?? throw new ValidationException('El filtro status no es válido.');
        }
        self::rejectUnknownFilters($filters);

        return $this->departures->list($tourId, $from, $to, $status);
    }

    public function create(User $actor, int $tourId, array $input): Departure
    {
        self::assertCanManage($actor);
        $tour = $this->tours->findById($tourId)
            ?? throw new NotFoundException('Tour no encontrado.');
        if (!$tour->isActive) {
            throw new ValidationException('No se pueden programar salidas para un tour inactivo.');
        }
        self::rejectUnknownFields($input, ['start_at', 'end_at', 'capacity']);
        $startAt = self::dateTime($input['start_at'] ?? null, 'start_at');
        $endAt = self::dateTime($input['end_at'] ?? null, 'end_at');
        if ($endAt <= $startAt) {
            throw new ValidationException(
                'La fecha y hora de fin debe ser posterior al inicio.',
                'end_at'
            );
        }
        $capacity = $input['capacity'] ?? null;
        if (!is_int($capacity) || $capacity < 1 || $capacity > 4294967295) {
            throw new ValidationException('La capacidad debe ser un entero mayor que cero.');
        }

        return $this->departures->create($tourId, $startAt, $endAt, $capacity);
    }

    public function transition(User $actor, int $id, array $input): Departure
    {
        self::assertCanManage($actor);
        self::rejectUnknownFields($input, ['status']);
        $statusValue = $input['status'] ?? null;
        if (!is_string($statusValue)) {
            throw new ValidationException('El campo status es obligatorio.');
        }
        $next = DepartureStatus::tryFrom($statusValue)
            ?? throw new ValidationException('El estado de la salida no es válido.');
        $current = $this->departures->findById($id)
            ?? throw new NotFoundException('Salida no encontrada.');
        if (!$current->status->canTransitionTo($next)) {
            throw new ValidationException(
                'Transición de estado no permitida desde ' . $current->status->value
                . ' hasta ' . $next->value . '.'
            );
        }

        return $this->departures->transitionStatus($id, $current->status, $next);
    }

    public function updateCapacity(User $actor, int $id, array $input): Departure
    {
        self::assertCanManage($actor);
        self::rejectUnknownFields($input, ['capacity']);
        $capacity = $input['capacity'] ?? null;
        if (!is_int($capacity) || $capacity < 1 || $capacity > 4294967295) {
            throw new ValidationException('La capacidad debe ser un entero mayor que cero.', 'capacity');
        }

        return $this->departures->updateCapacity($id, $capacity);
    }

    public function updateSchedule(User $actor, int $id, array $input): Departure
    {
        self::assertCanManage($actor);
        self::rejectUnknownFields($input, ['start_at', 'end_at']);
        if (!array_key_exists('start_at', $input) || !array_key_exists('end_at', $input)) {
            throw new ValidationException('Indica la fecha de inicio y fin.', 'start_at');
        }
        $startAt = self::dateTime($input['start_at'], 'start_at');
        $endAt = self::dateTime($input['end_at'], 'end_at');
        if ($endAt <= $startAt) {
            throw new ValidationException('La fecha y hora de fin debe ser posterior al inicio.', 'end_at');
        }

        return $this->departures->updateSchedule($id, $startAt, $endAt);
    }

    private static function dateFilter(mixed $value, string $field): string
    {
        if (
            !is_string($value)
            || !preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $value)
            || (int) substr($value, 0, 4) < 1000
        ) {
            throw new ValidationException(
                'La fecha debe existir y estar entre los años 1000 y 9999.',
                $field
            );
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (
            $date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value
        ) {
            throw new ValidationException(
                'La fecha debe existir y estar entre los años 1000 y 9999.',
                $field
            );
        }

        return $value;
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

    private static function rejectUnknownFilters(array $filters): void
    {
        self::rejectUnknownFields($filters, ['from', 'to', 'status', 'tour_id']);
    }

    private static function rejectUnknownFields(array $input, array $allowed): void
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
}
