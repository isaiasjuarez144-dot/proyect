<?php

declare(strict_types=1);

namespace UyuniTour\Infrastructure\Database;

use PDO;
use UyuniTour\Domain\Reservations\Tourist;
use UyuniTour\Domain\Reservations\TouristRepository;
use UyuniTour\Domain\Shared\NotFoundException;

final class PdoTouristRepository implements TouristRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function create(string $fullName, ?string $email, ?string $phone): Tourist
    {
        $statement = $this->connection->prepare(
            'INSERT INTO tourists (full_name, email, phone) VALUES (:full_name, :email, :phone)'
        );
        $statement->execute(['full_name' => $fullName, 'email' => $email, 'phone' => $phone]);

        return $this->findById((int) $this->connection->lastInsertId())
            ?? throw new \RuntimeException('Created tourist could not be reloaded.');
    }

    public function update(int $id, string $fullName, ?string $email, ?string $phone): Tourist
    {
        $statement = $this->connection->prepare(
            'UPDATE tourists SET full_name = :full_name, email = :email, phone = :phone WHERE id = :id'
        );
        $statement->execute([
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $phone,
            'id' => $id,
        ]);
        $tourist = $this->findById($id);
        if ($tourist === null) {
            throw new NotFoundException('Turista no encontrado.');
        }

        return $tourist;
    }

    public function list(): array
    {
        $records = $this->connection->query(
            'SELECT id, full_name, email, phone, created_at, updated_at FROM tourists ORDER BY full_name, id'
        )->fetchAll();

        return array_map(self::fromRecord(...), $records);
    }

    private function findById(int $id): ?Tourist
    {
        $statement = $this->connection->prepare(
            'SELECT id, full_name, email, phone, created_at, updated_at FROM tourists WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
        $record = $statement->fetch();

        return $record === false ? null : self::fromRecord($record);
    }

    private static function fromRecord(array $record): Tourist
    {
        return new Tourist(
            (int) $record['id'],
            (string) $record['full_name'],
            $record['email'] === null ? null : (string) $record['email'],
            $record['phone'] === null ? null : (string) $record['phone'],
            (string) $record['created_at'],
            (string) $record['updated_at']
        );
    }
}
