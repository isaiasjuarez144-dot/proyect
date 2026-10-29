<?php

declare(strict_types=1);

namespace UyuniTour\Domain\Payments;

interface PaymentRepository
{
    public function create(
        int $actorId,
        int $reservationId,
        string $idempotencyKey,
        string $requestHash,
        int $amountCents,
        string $paidAt,
        string $method,
        ?string $reference
    ): Payment;

    public function listForReservation(int $reservationId): array;

    public function void(int $actorId, int $paymentId, string $reason): Payment;
}
