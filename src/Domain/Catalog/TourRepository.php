<?php

declare(strict_types=1);

namespace UyuniTour\Domain\Catalog;

interface TourRepository
{
    public function list(): array;

    public function findById(int $id): ?Tour;

    public function create(
        int $actorId,
        string $name,
        string $description,
        string $itinerary,
        int $durationDays,
        string $basePrice
    ): Tour;

    public function update(
        int $actorId,
        int $id,
        string $name,
        string $description,
        string $itinerary,
        int $durationDays,
        string $basePrice,
        bool $isActive
    ): Tour;
}
