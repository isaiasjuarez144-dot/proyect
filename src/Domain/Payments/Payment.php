<?php

declare(strict_types=1);

namespace UyuniTour\Domain\Payments;

final readonly class Payment
{
    public function __construct(
        public int $id,
        public int $reservationId,
        public string $amount,
        public string $paidAt,
        public string $method,
        public ?string $reference,
        public int $createdBy,
        public string $status,
        public ?string $voidedAt,
        public ?int $voidedBy,
        public ?string $voidReason,
        public string $createdAt
    ) {
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
