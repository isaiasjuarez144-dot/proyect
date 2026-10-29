<?php

declare(strict_types=1);

namespace UyuniTour\Domain\Reservations;

final readonly class Tourist
{
    public function __construct(
        public int $id,
        public string $fullName,
        public ?string $email,
        public ?string $phone,
        public string $createdAt,
        public string $updatedAt
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->fullName,
            'email' => $this->email,
            'phone' => $this->phone,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
