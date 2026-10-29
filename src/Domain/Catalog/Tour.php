<?php

declare(strict_types=1);

namespace UyuniTour\Domain\Catalog;

final readonly class Tour
{
    public function __construct(
        public int $id,
        public string $name,
        public string $description,
        public string $itinerary,
        public int $durationDays,
        public string $basePrice,
        public bool $isActive,
        public string $createdAt,
        public string $updatedAt
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'itinerary' => $this->itinerary,
            'duration_days' => $this->durationDays,
            'base_price' => $this->basePrice,
            'active' => $this->isActive,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
