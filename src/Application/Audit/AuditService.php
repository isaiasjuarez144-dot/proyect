<?php

declare(strict_types=1);

namespace UyuniTour\Application\Audit;

use DateTimeImmutable;
use UyuniTour\Domain\Audit\AuditRepository;
use UyuniTour\Domain\Identity\Role;
use UyuniTour\Domain\Identity\User;
use UyuniTour\Domain\Shared\AuthorizationException;
use UyuniTour\Domain\Shared\ValidationException;

final class AuditService
{
    public function __construct(private readonly AuditRepository $events)
    {
    }

    public function list(User $actor, array $filters): array
    {
        if (!in_array($actor->role, [Role::Administrator, Role::Auditor], true)) {
            throw new AuthorizationException('Permiso insuficiente.');
        }
        foreach (array_keys($filters) as $field) {
            if (!in_array($field, ['from', 'to', 'entity', 'action', 'actor_id', 'page', 'per_page'], true)) {
                throw new ValidationException('El filtro ' . $field . ' no está permitido.');
            }
        }
        $from = self::date($filters['from'] ?? null, 'from');
        $to = self::date($filters['to'] ?? null, 'to');
        if ($from !== null && $to !== null && $from > $to) {
            throw new ValidationException('Desde no puede ser posterior a Hasta.');
        }
        $entity = self::choice($filters['entity'] ?? null, [
            'reservation',
            'tour',
            'payment',
            'assignment',
            'maintenance',
            'vehicle',
        ], 'entity');
        $action = self::choice($filters['action'] ?? null, [
            'created',
            'updated',
            'cancelled',
            'voided',
        ], 'action');
        $actorId = self::positiveInteger($filters['actor_id'] ?? null, 'actor_id');
        $page = self::positiveInteger($filters['page'] ?? 1, 'page') ?? 1;
        $perPage = self::positiveInteger($filters['per_page'] ?? 20, 'per_page') ?? 20;
        if ($perPage > 100) {
            throw new ValidationException('La paginación no es válida.');
        }

        return $this->events->search($from, $to, $entity, $action, $actorId, $page, $perPage);
    }

    private static function date(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || !preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $value)) {
            throw new ValidationException('La fecha no es válida.', $field);
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (
            $date === false
            || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))
            || $date->format('Y-m-d') !== $value
            || (int) substr($value, 0, 4) < 1000
        ) {
            throw new ValidationException('La fecha no es válida.', $field);
        }

        return $value;
    }

    private static function choice(mixed $value, array $allowed, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            throw new ValidationException('El filtro no es válido.', $field);
        }

        return $value;
    }

    private static function positiveInteger(mixed $value, string $field): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value) && $value >= 1) {
            return $value;
        }
        if (is_string($value) && preg_match('/\A[1-9][0-9]*\z/', $value)) {
            return (int) $value;
        }

        throw new ValidationException(
            $field === 'actor_id' ? 'El actor no es válido.' : 'La paginación no es válida.',
            $field
        );
    }
}
