<?php

declare(strict_types=1);

namespace UyuniTour\Infrastructure\Database;

use PDO;
use PDOException;
use Throwable;
use UyuniTour\Domain\Payments\Payment;
use UyuniTour\Domain\Payments\PaymentRepository;
use UyuniTour\Domain\Audit\AuditRepository;
use UyuniTour\Domain\Shared\ConflictException;
use UyuniTour\Domain\Shared\NotFoundException;

final class PdoPaymentRepository implements PaymentRepository
{
    public function __construct(
        private readonly PDO $connection,
        private readonly ?AuditRepository $audit = null
    ) {
    }

    public function create(
        int $actorId,
        int $reservationId,
        string $idempotencyKey,
        string $requestHash,
        int $amountCents,
        string $paidAt,
        string $method,
        ?string $reference
    ): Payment {
        $this->connection->beginTransaction();
        try {
            $reservation = $this->lockReservation($reservationId);
            $existing = $this->findByKey($actorId, $idempotencyKey);
            if ($existing !== null) {
                if ($existing['request_hash'] !== $requestHash) {
                    throw new ConflictException('La clave de idempotencia ya se usó con otros datos.');
                }
                $this->connection->commit();
                return $this->find($existing['id']);
            }
            if ($reservation['status'] !== 'confirmada') {
                throw new ConflictException('No se puede registrar un pago para una reserva cancelada.');
            }
            $paid = $this->sumApplied((int) $reservation['id']);
            $total = self::cents((string) $reservation['total_price']);
            if ($amountCents > $total - $paid) {
                throw new ConflictException('El monto supera el saldo pendiente de la reserva.');
            }
            $statement = $this->connection->prepare(
                'INSERT INTO payments (reservation_id, amount, paid_at, method, reference, created_by, '
                . 'idempotency_key, request_hash) VALUES (:reservation_id, :amount, :paid_at, :method, '
                . ':reference, :created_by, :key, :hash)'
            );
            $statement->execute([
                'reservation_id' => $reservationId, 'amount' => self::format($amountCents),
                'paid_at' => $paidAt, 'method' => $method, 'reference' => $reference,
                'created_by' => $actorId, 'key' => $idempotencyKey, 'hash' => $requestHash,
            ]);
            $id = (int) $this->connection->lastInsertId();
            $this->audit?->record(
                $actorId,
                'created',
                'payment',
                $id,
                null,
                ['reservation_id' => $reservationId, 'amount' => self::format($amountCents), 'method' => $method]
            );
            $this->connection->commit();
            return $this->find($id);
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            if ($exception instanceof PDOException && $exception->getCode() === '23000') {
                $existing = $this->findByKey($actorId, $idempotencyKey);
                if ($existing !== null && $existing['request_hash'] === $requestHash) {
                    return $this->find($existing['id']);
                }
            }
            throw $exception;
        }
    }

    public function listForReservation(int $reservationId): array
    {
        $statement = $this->connection->prepare(
            'SELECT * FROM payments WHERE reservation_id = :id ORDER BY paid_at, id'
        );
        $statement->execute(['id' => $reservationId]);
        return array_map(self::map(...), $statement->fetchAll());
    }

    public function void(int $actorId, int $paymentId, string $reason): Payment
    {
        $this->connection->beginTransaction();
        try {
            $statement = $this->connection->prepare('SELECT * FROM payments WHERE id = :id FOR UPDATE');
            $statement->execute(['id' => $paymentId]);
            $payment = $statement->fetch();
            if ($payment === false) {
                throw new NotFoundException('Pago no encontrado.');
            }
            if ($payment['status'] === 'anulado') {
                throw new ConflictException('El pago ya fue anulado.');
            }
            $update = $this->connection->prepare(
                'UPDATE payments SET status = \'anulado\', voided_at = UTC_TIMESTAMP(), '
                . 'voided_by = :voided_by, void_reason = :reason WHERE id = :id'
            );
            $update->execute(['voided_by' => $actorId, 'reason' => $reason, 'id' => $paymentId]);
            $this->audit?->record(
                $actorId,
                'voided',
                'payment',
                $paymentId,
                ['status' => 'aplicado'],
                ['status' => 'anulado', 'reason' => $reason]
            );
            $this->connection->commit();
            return $this->find($paymentId);
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    private function lockReservation(int $id): array
    {
        $statement = $this->connection->prepare('SELECT * FROM reservations WHERE id = :id FOR UPDATE');
        $statement->execute(['id' => $id]);
        $reservation = $statement->fetch();
        if ($reservation === false) {
            throw new NotFoundException('Reserva no encontrada.');
        }
        return $reservation;
    }

    private function findByKey(int $actorId, string $key): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT * FROM payments WHERE created_by = :actor AND idempotency_key = :key'
        );
        $statement->execute(['actor' => $actorId, 'key' => $key]);
        $payment = $statement->fetch();
        return $payment === false ? null : $payment;
    }

    private function find(int $id): Payment
    {
        $statement = $this->connection->prepare('SELECT * FROM payments WHERE id = :id');
        $statement->execute(['id' => $id]);
        $payment = $statement->fetch();
        if ($payment === false) {
            throw new NotFoundException('Pago no encontrado.');
        }
        return self::map($payment);
    }

    private function sumApplied(int $reservationId): int
    {
        $statement = $this->connection->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE reservation_id = :id AND status = 'aplicado'"
        );
        $statement->execute(['id' => $reservationId]);
        return self::cents((string) $statement->fetchColumn());
    }

    private static function map(array $row): Payment
    {
        return new Payment(
            (int) $row['id'],
            (int) $row['reservation_id'],
            (string) $row['amount'],
            (string) $row['paid_at'],
            (string) $row['method'],
            $row['reference'],
            (int) $row['created_by'],
            (string) $row['status'],
            $row['voided_at'] ?? null,
            isset($row['voided_by']) ? (int) $row['voided_by'] : null,
            $row['void_reason'] ?? null,
            (string) $row['created_at']
        );
    }

    private static function cents(string $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private static function format(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0');
    }
}
