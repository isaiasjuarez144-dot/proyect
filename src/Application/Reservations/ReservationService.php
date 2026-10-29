<?php

declare(strict_types=1);

namespace UyuniTour\Application\Reservations;

use DateTimeImmutable;
use UyuniTour\Domain\Identity\Role;
use UyuniTour\Domain\Identity\User;
use UyuniTour\Domain\Reservations\Reservation;
use UyuniTour\Domain\Reservations\ReservationRepository;
use UyuniTour\Domain\Shared\AuthorizationException;
use UyuniTour\Domain\Shared\ValidationException;

final class ReservationService
{
    public function __construct(private readonly ReservationRepository $reservations)
    {
    }

    public function list(User $actor): array
    {
        self::assertCanManage($actor);

        return $this->reservations->list();
    }

    public function create(User $actor, array $input, ?string $idempotencyKey): Reservation
    {
        self::assertCanManage($actor);
        if (
            $idempotencyKey === null
            || !preg_match('/\A[A-Za-z0-9._:-]{8,128}\z/', $idempotencyKey)
        ) {
            throw new ValidationException('Se requiere una clave Idempotency-Key válida.');
        }
        self::rejectUnknown($input, ['departure_id', 'tourist_id', 'passengers', 'price_per_person', 'payment']);
        $departureId = self::positiveInteger($input['departure_id'] ?? null, 'departure_id');
        $touristId = self::positiveInteger($input['tourist_id'] ?? null, 'tourist_id');
        $passengers = self::passengers($input['passengers'] ?? null);
        $price = self::priceCents($input['price_per_person'] ?? null);
        $payment = null;
        if (isset($input['payment'])) {
            if ($input['payment'] instanceof \stdClass) {
                $input['payment'] = get_object_vars($input['payment']);
            }
            if (!is_array($input['payment'])) {
                throw new ValidationException('El anticipo debe ser un objeto válido.', 'payment');
            }
            $payment = $input['payment'];
            if (!isset($payment['idempotency_key']) || !is_string($payment['idempotency_key'])) {
                throw new ValidationException('El anticipo requiere una clave de idempotencia.', 'payment');
            }
            $payment['amount_cents'] = self::priceCents($payment['amount'] ?? null);
            if (
                $payment['amount_cents'] < 1 || !preg_match(
                    '/\A[A-Za-z0-9._:-]{8,128}\z/',
                    $payment['idempotency_key']
                )
            ) {
                throw new ValidationException('El anticipo debe tener monto y clave válidos.', 'payment');
            }
            if ($payment['amount_cents'] < 1) {
                throw new ValidationException('El anticipo debe tener un monto positivo.', 'payment.amount');
            }
            $payment['paid_at'] = self::paymentDate($payment['paid_at'] ?? null);
            $payment['method'] = $payment['method'] ?? null;
            if (!in_array($payment['method'], ['efectivo', 'transferencia', 'qr', 'tarjeta', 'otro'], true)) {
                throw new ValidationException('El método del anticipo no es válido.', 'payment.method');
            }
            $payment['reference'] = $payment['reference'] ?? null;
            if (
                $payment['reference'] !== null
                && (!is_string($payment['reference']) || mb_strlen(trim($payment['reference'])) > 120)
            ) {
                throw new ValidationException(
                    'La referencia debe tener hasta 120 caracteres.',
                    'payment.reference'
                );
            }
            if (is_string($payment['reference'])) {
                $payment['reference'] = trim($payment['reference']);
            }
        }
        $hash = hash('sha256', json_encode(
            [$departureId, $touristId, $passengers, $price, $payment],
            JSON_THROW_ON_ERROR
        ));

        return $this->reservations->create(
            $actor->id,
            $idempotencyKey,
            $hash,
            $departureId,
            $touristId,
            $passengers,
            $price,
            $payment
        );
    }

    public function update(User $actor, int $id, array $input): Reservation
    {
        self::assertCanManage($actor);
        self::rejectUnknown($input, ['departure_id', 'tourist_id', 'passengers']);
        $departureId = self::positiveInteger($input['departure_id'] ?? null, 'departure_id');
        $touristId = self::positiveInteger($input['tourist_id'] ?? null, 'tourist_id');
        $passengers = self::passengers($input['passengers'] ?? null);

        return $this->reservations->update($actor->id, $id, $departureId, $touristId, $passengers);
    }

    public function cancel(User $actor, int $id): Reservation
    {
        self::assertCanManage($actor);

        return $this->reservations->cancel($actor->id, $id);
    }

    private static function passengers(mixed $value): array
    {
        if (!is_array($value) || count($value) < 1 || count($value) > 500) {
            throw new ValidationException('Registra entre 1 y 500 pasajeros.', 'passengers');
        }
        $names = [];
        foreach ($value as $passenger) {
            if (!is_string($passenger) || trim($passenger) === '' || mb_strlen(trim($passenger)) > 150) {
                throw new ValidationException('Cada pasajero debe tener un nombre válido.', 'passengers');
            }
            $names[] = trim($passenger);
        }

        return $names;
    }

    private static function positiveInteger(mixed $value, string $field): int
    {
        if (!is_int($value) || $value < 1) {
            throw new ValidationException('El campo ' . $field . ' debe ser un entero positivo.', $field);
        }

        return $value;
    }

    private static function priceCents(mixed $value): int
    {
        if (!is_string($value) && !is_int($value)) {
            throw new ValidationException(
                'El precio pactado debe ser un monto decimal válido.',
                'price_per_person'
            );
        }
        $value = (string) $value;
        if (!preg_match('/\A(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?\z/', $value)) {
            throw new ValidationException(
                'El precio debe tener hasta dos decimales y no ser negativo.',
                'price_per_person'
            );
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private static function paymentDate(mixed $value): string
    {
        if (!is_string($value)) {
            throw new ValidationException('La fecha del anticipo no es válida.', 'payment.paid_at');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (
            $date === false
            || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))
            || $date->format('Y-m-d\TH:i') !== $value
        ) {
            throw new ValidationException('La fecha del anticipo no es válida.', 'payment.paid_at');
        }

        return $date->format('Y-m-d H:i:s');
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
}
