<?php

declare(strict_types=1);

namespace UyuniTour\Domain\Operations;

interface FleetRepository
{
    public function createVehicle(string $plate, int $capacity, int $actorId): array;

    public function listVehicles(): array;

    public function updateVehicle(int $id, array $changes, int $actorId): array;

    public function listDrivers(): array;

    public function listAssignments(): array;

    public function assign(int $departureId, int $vehicleId, int $driverId, int $actorId): array;

    public function createMaintenance(
        int $vehicleId,
        string $startAt,
        string $endAt,
        string $description,
        int $actorId
    ): array;

    public function listMaintenance(?int $vehicleId): array;

    public function transitionMaintenance(int $id, string $nextStatus, int $actorId): array;

    public function createIncident(
        int $departureId,
        int $actorId,
        string $occurredAt,
        string $description
    ): array;

    public function listIncidents(?int $departureId, ?int $driverId): array;

    public function listDriverDepartures(int $driverId): array;
}
