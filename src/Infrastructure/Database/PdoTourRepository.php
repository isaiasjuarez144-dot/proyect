<?php

declare(strict_types=1);

namespace UyuniTour\Infrastructure\Database;

use PDO;
use UyuniTour\Domain\Catalog\Tour;
use UyuniTour\Domain\Catalog\TourRepository;
use UyuniTour\Domain\Audit\AuditRepository;
use UyuniTour\Domain\Shared\NotFoundException;

final class PdoTourRepository implements TourRepository
{
    public function __construct(
        private readonly PDO $connection,
        private readonly ?AuditRepository $audit = null
    ) {
    }

    public function list(): array
    {
        $records = $this->connection->query(
            'SELECT id, name, description, itinerary, duration_days, base_price, '
            . 'is_active, created_at, updated_at FROM tours ORDER BY name, id'
        )->fetchAll();

        return array_map(self::toTour(...), $records);
    }

    public function findById(int $id): ?Tour
    {
        $statement = $this->connection->prepare(
            'SELECT id, name, description, itinerary, duration_days, base_price, '
            . 'is_active, created_at, updated_at FROM tours WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
        $record = $statement->fetch();

        return $record === false ? null : self::toTour($record);
    }

    public function create(
        int $actorId,
        string $name,
        string $description,
        string $itinerary,
        int $durationDays,
        string $basePrice
    ): Tour {
        $this->connection->beginTransaction();
        try {
            $statement = $this->connection->prepare(
                'INSERT INTO tours (name, description, itinerary, duration_days, base_price) '
                . 'VALUES (:name, :description, :itinerary, :duration_days, :base_price)'
            );
            $statement->execute([
            'name' => $name,
            'description' => $description,
            'itinerary' => $itinerary,
            'duration_days' => $durationDays,
            'base_price' => $basePrice,
            ]);
            $id = (int) $this->connection->lastInsertId();
            $tour = $this->findById($id) ?? throw new \RuntimeException('Created tour could not be reloaded.');
            $this->audit?->record($actorId, 'created', 'tour', $id, null, self::values($tour));
            $this->connection->commit();
            return $tour;
        } catch (\Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function update(
        int $actorId,
        int $id,
        string $name,
        string $description,
        string $itinerary,
        int $durationDays,
        string $basePrice,
        bool $isActive
    ): Tour {
        $this->connection->beginTransaction();
        try {
            $current = $this->findById($id) ?? throw new NotFoundException('Tour no encontrado.');
            $statement = $this->connection->prepare(
                'UPDATE tours SET name = :name, description = :description, itinerary = :itinerary, '
                . 'duration_days = :duration_days, base_price = :base_price, is_active = :is_active '
                . 'WHERE id = :id'
            );
            $statement->execute([
            'name' => $name,
            'description' => $description,
            'itinerary' => $itinerary,
            'duration_days' => $durationDays,
            'base_price' => $basePrice,
            'is_active' => (int) $isActive,
            'id' => $id,
            ]);

            $updated = $this->findById($id) ?? throw new NotFoundException('Tour no encontrado.');
            $this->audit?->record(
                $actorId,
                'updated',
                'tour',
                $id,
                self::values($current),
                self::values($updated)
            );
            $this->connection->commit();
            return $updated;
        } catch (\Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    private static function values(Tour $tour): array
    {
        return [
            'name' => $tour->name,
            'description' => $tour->description,
            'itinerary' => $tour->itinerary,
            'duration_days' => $tour->durationDays,
            'base_price' => $tour->basePrice,
            'active' => $tour->isActive,
        ];
    }

    private static function toTour(array $record): Tour
    {
        return new Tour(
            (int) $record['id'],
            (string) $record['name'],
            (string) $record['description'],
            (string) $record['itinerary'],
            (int) $record['duration_days'],
            (string) $record['base_price'],
            (bool) $record['is_active'],
            (string) $record['created_at'],
            (string) $record['updated_at']
        );
    }
}
