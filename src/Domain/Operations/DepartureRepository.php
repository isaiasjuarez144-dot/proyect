<?php

declare(strict_types=1);

namespace UyuniTour\Domain\Operations;

interface DepartureRepository
{
    public function create(
        int $tourId,
        string $startAt,
        string $endAt,
        int $capacity
    ): Departure;

    public function list(
        ?int $tourId,
        ?string $from,
        ?string $to,
        ?DepartureStatus $status
    ): array;

    public function findById(int $id): ?Departure;

    public function transitionStatus(
        int $id,
        DepartureStatus $current,
        DepartureStatus $next
    ): Departure;

    public function updateCapacity(int $id, int $capacity): Departure;

    public function updateSchedule(int $id, string $startAt, string $endAt): Departure;
}
