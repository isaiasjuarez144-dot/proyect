<?php

declare(strict_types=1);

namespace UyuniTour\Domain\Reservations;

interface ReservationRepository
{
    public function create(
        int $actorId,
        string $idempotencyKey,
        string $payloadHash,
        int $departureId,
        int $touristId,
        array $passengers,
        int $priceCents,
        ?array $payment = null
    ): Reservation;

    public function update(
        int $actorId,
        int $id,
        int $departureId,
        int $touristId,
        array $passengers
    ): Reservation;

    public function cancel(int $actorId, int $id): Reservation;

    public function list(): array;

    public function findById(int $id): ?Reservation;
}
