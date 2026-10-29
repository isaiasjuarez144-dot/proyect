<?php

declare(strict_types=1);

namespace UyuniTour\Domain\Operations;

final readonly class Departure
{
    public function __construct(
        public int $id,
        public int $tourId,
        public string $tourName,
        public string $startAt,
        public string $endAt,
        public int $capacity,
        public int $occupiedSeats,
        public int $availableSeats,
        public DepartureStatus $status,
        public string $createdAt,
        public string $updatedAt
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tour_id' => $this->tourId,
            'tour_name' => $this->tourName,
            'start_at' => $this->startAt,
            'end_at' => $this->endAt,
            'capacity' => $this->capacity,
            'occupied_seats' => $this->occupiedSeats,
            'available_seats' => $this->availableSeats,
            'status' => $this->status->value,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
