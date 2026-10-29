<?php

declare(strict_types=1);

namespace UyuniTour\Domain\Identity;

final readonly class User
{
    public function __construct(
        public int $id,
        public string $email,
        public Role $role,
        public bool $isActive,
        public string $createdAt,
        public string $updatedAt
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'role' => $this->role->value,
            'active' => $this->isActive,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
