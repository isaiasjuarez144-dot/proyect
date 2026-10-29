<?php

declare(strict_types=1);

namespace UyuniTour\Domain\Audit;

interface AuditRepository
{
    public function record(
        int $actorId,
        string $action,
        string $entity,
        int $entityId,
        ?array $oldValues,
        ?array $newValues
    ): void;

    public function search(
        ?string $from,
        ?string $to,
        ?string $entity,
        ?string $action,
        ?int $actorId,
        int $page,
        int $perPage
    ): array;
}
