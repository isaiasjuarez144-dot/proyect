<?php

declare(strict_types=1);

namespace UyuniTour\Application\Payments;

use DateTimeImmutable;
use UyuniTour\Domain\Identity\Role;
use UyuniTour\Domain\Identity\User;
use UyuniTour\Domain\Payments\PaymentRepository;
use UyuniTour\Domain\Shared\AuthorizationException;
use UyuniTour\Domain\Shared\ValidationException;

final class PaymentService
{
    public function __construct(private readonly PaymentRepository $payments)
    {
    }

    public function create(
        User $actor,
        int $reservationId,
        array $input,
        ?string $key
    ): \UyuniTour\Domain\Payments\Payment {
        self::assertCanRegister($actor);
        if ($key === null || !preg_match('/\A[A-Za-z0-9._:-]{8,128}\z/', $key)) {
            throw new ValidationException('Se requiere una clave Idempotency-Key válida.');
        }
        self::rejectUnknown($input, ['amount', 'paid_at', 'method', 'reference']);
        $amount = self::cents($input['amount'] ?? null);
        $paidAt = self::dateTime($input['paid_at'] ?? null);
        $method = $input['method'] ?? null;
        if (
            !is_string($method)
            || !in_array($method, ['efectivo', 'transferencia', 'qr', 'tarjeta', 'otro'], true)
        ) {
            throw new ValidationException('El método de pago no es válido.', 'method');
        }
        $reference = $input['reference'] ?? null;
        if ($reference !== null && (!is_string($reference) || mb_strlen(trim($reference)) > 120)) {
            throw new ValidationException('La referencia debe tener hasta 120 caracteres.', 'reference');
        }
        $hash = hash(
            'sha256',
            json_encode([$reservationId, $amount, $paidAt, $method, $reference], JSON_THROW_ON_ERROR)
        );

        return $this->payments->create($actor->id, $reservationId, $key, $hash, $amount, $paidAt, $method, $reference);
    }

    public function list(User $actor, int $reservationId): array
    {
        self::assertCanRegister($actor);
        return $this->payments->listForReservation($reservationId);
    }

    public function void(User $actor, int $paymentId, array $input): \UyuniTour\Domain\Payments\Payment
    {
        if ($actor->role !== Role::Administrator) {
            throw new AuthorizationException('Solo un administrador puede anular pagos.');
        }
        self::rejectUnknown($input, ['reason']);
        $reason = $input['reason'] ?? null;
        if (!is_string($reason) || trim($reason) === '' || mb_strlen(trim($reason)) > 500) {
            throw new ValidationException('Indica el motivo de la anulación.', 'reason');
        }
        return $this->payments->void($actor->id, $paymentId, trim($reason));
    }

    private static function assertCanRegister(User $actor): void
    {
        if (!in_array($actor->role, [Role::Administrator, Role::Operator], true)) {
            throw new AuthorizationException('Permiso insuficiente.');
        }
    }

    private static function cents(mixed $value): int
    {
        if (!is_string($value) && !is_int($value)) {
            throw new ValidationException('El monto debe ser positivo y tener hasta dos decimales.', 'amount');
        }
        $text = (string) $value;
        if (!preg_match('/\A[1-9][0-9]{0,9}(?:\.[0-9]{1,2})?\z/', $text)) {
            throw new ValidationException('El monto debe ser positivo y tener hasta dos decimales.', 'amount');
        }
        [$whole, $fraction] = array_pad(explode('.', $text, 2), 2, '');
        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private static function dateTime(mixed $value): string
    {
        if (!is_string($value)) {
            throw new ValidationException('La fecha del pago no es válida.', 'paid_at');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (
            $date === false || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))
            || $date->format('Y-m-d\TH:i') !== $value
        ) {
            throw new ValidationException('La fecha del pago no es válida.', 'paid_at');
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
}
