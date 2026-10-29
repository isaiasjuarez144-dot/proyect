<?php

declare(strict_types=1);

namespace UyuniTour\Infrastructure\Database;

use DateTimeImmutable;
use PDO;
use Throwable;
use UyuniTour\Domain\Auth\LoginAttemptRepository;

final class PdoLoginAttemptRepository implements LoginAttemptRepository
{
    private const WINDOW_MINUTES = 15;
    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly PDO $connection)
    {
    }

    public function isLocked(string $identifierHash): bool
    {
        $statement = $this->connection->prepare(
            'SELECT attempts, first_attempt_at, locked_until '
            . 'FROM login_attempts WHERE identifier_hash = :identifier_hash'
        );
        $statement->execute(['identifier_hash' => $identifierHash]);
        $record = $statement->fetch();
        if ($record === false) {
            return false;
        }

        $lockedUntil = $record['locked_until'] === null
            ? null
            : new DateTimeImmutable((string) $record['locked_until']);
        if ($lockedUntil !== null && $lockedUntil > new DateTimeImmutable()) {
            return true;
        }

        $firstAttempt = new DateTimeImmutable((string) $record['first_attempt_at']);
        if ($firstAttempt < new DateTimeImmutable('-' . self::WINDOW_MINUTES . ' minutes')) {
            $this->clear($identifierHash);

            return false;
        }

        return false;
    }

    public function recordFailure(string $identifierHash): void
    {
        $this->connection->beginTransaction();
        try {
            $insert = $this->connection->prepare(
                'INSERT IGNORE INTO login_attempts (identifier_hash) VALUES (:identifier_hash)'
            );
            $insert->execute(['identifier_hash' => $identifierHash]);
            $select = $this->connection->prepare(
                'SELECT attempts, first_attempt_at FROM login_attempts '
                . 'WHERE identifier_hash = :identifier_hash FOR UPDATE'
            );
            $select->execute(['identifier_hash' => $identifierHash]);
            $record = $select->fetch();
            $now = new DateTimeImmutable();
            $windowExpired = $record === false
                || new DateTimeImmutable((string) $record['first_attempt_at'])
                    < $now->modify('-' . self::WINDOW_MINUTES . ' minutes');
            $attempts = $windowExpired ? 1 : (int) $record['attempts'] + 1;
            $firstAttempt = $windowExpired
                ? $now->format('Y-m-d H:i:s')
                : (string) $record['first_attempt_at'];
            $lockedUntil = $attempts >= self::MAX_ATTEMPTS
                ? $now->modify('+' . self::WINDOW_MINUTES . ' minutes')->format('Y-m-d H:i:s')
                : null;

            $update = $this->connection->prepare(
                'UPDATE login_attempts SET attempts = :attempts, first_attempt_at = :first_attempt_at, '
                . 'locked_until = :locked_until WHERE identifier_hash = :identifier_hash'
            );
            $update->execute([
                'attempts' => $attempts,
                'first_attempt_at' => $firstAttempt,
                'locked_until' => $lockedUntil,
                'identifier_hash' => $identifierHash,
            ]);
            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function clear(string $identifierHash): void
    {
        $statement = $this->connection->prepare(
            'DELETE FROM login_attempts WHERE identifier_hash = :identifier_hash'
        );
        $statement->execute(['identifier_hash' => $identifierHash]);
    }
}
