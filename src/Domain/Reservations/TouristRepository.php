<?php

declare(strict_types=1);

namespace UyuniTour\Domain\Reservations;

interface TouristRepository
{
    public function create(string $fullName, ?string $email, ?string $phone): Tourist;

    public function update(int $id, string $fullName, ?string $email, ?string $phone): Tourist;

    public function list(): array;
}
