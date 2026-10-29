<?php

declare(strict_types=1);

namespace UyuniTour\Domain\Reservations;

final readonly class Reservation
{
    public function __construct(
        public int $id,
        public int $departureId,
        public string $tourName,
        public string $departureStartAt,
        public int $touristId,
        public string $touristName,
        public int $passengerCount,
        public array $passengers,
        public string $pricePerPerson,
        public string $totalPrice,
        public string $paidNet,
        public string $balanceDue,
        public ReservationStatus $status,
        public string $createdAt,
        public string $updatedAt
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'departure_id' => $this->departureId,
            'tour_name' => $this->tourName,
            'departure_start_at' => $this->departureStartAt,
            'tourist_id' => $this->touristId,
            'tourist_name' => $this->touristName,
            'passenger_count' => $this->passengerCount,
            'passengers' => $this->passengers,
            'price_per_person' => $this->pricePerPerson,
            'total_price' => $this->totalPrice,
            'paid_net' => $this->paidNet,
            'balance_due' => $this->balanceDue,
            'status' => $this->status->value,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
