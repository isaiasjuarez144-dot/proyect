<?php

declare(strict_types=1);

namespace UyuniTour\Infrastructure\Database;

use PDO;
use UyuniTour\Domain\Audit\AuditRepository;

final class PdoAuditRepository implements AuditRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function record(
        int $actorId,
        string $action,
        string $entity,
        int $entityId,
        ?array $oldValues,
        ?array $newValues
    ): void {
        $statement = $this->connection->prepare(
            'INSERT INTO audit_events '
            . '(actor_id, action, entity, entity_id, old_values, new_values) '
            . 'VALUES (:actor_id, :action, :entity, :entity_id, :old_values, :new_values)'
        );
        $statement->execute([
            'actor_id' => $actorId,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'old_values' => $oldValues === null ? null : json_encode($oldValues, JSON_THROW_ON_ERROR),
            'new_values' => $newValues === null ? null : json_encode($newValues, JSON_THROW_ON_ERROR),
        ]);
    }

    public function search(
        ?string $from,
        ?string $to,
        ?string $entity,
        ?string $action,
        ?int $actorId,
        int $page,
        int $perPage
    ): array {
        $where = [];
        $parameters = [];
        if ($from !== null) {
            $where[] = 'a.occurred_at >= :from';
            $parameters['from'] = $from . ' 00:00:00';
        }
        if ($to !== null) {
            $where[] = 'a.occurred_at <= :to';
            $parameters['to'] = $to . ' 23:59:59';
        }
        foreach (['entity', 'action'] as $field) {
            if ($$field !== null) {
                $where[] = 'a.' . $field . ' = :' . $field;
                $parameters[$field] = $$field;
            }
        }
        if ($actorId !== null) {
            $where[] = 'a.actor_id = :actor_id';
            $parameters['actor_id'] = $actorId;
        }
        $condition = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $count = $this->connection->prepare('SELECT COUNT(*) FROM audit_events a' . $condition);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $offset = ($page - 1) * $perPage;
        $statement = $this->connection->prepare(
            'SELECT a.id, a.action, a.entity, a.entity_id, a.occurred_at, '
            . 'a.old_values, a.new_values, u.email AS actor_email '
            . 'FROM audit_events a INNER JOIN users u ON u.id = a.actor_id'
            . $condition . ' ORDER BY a.occurred_at DESC, a.id DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $key => $value) {
            $statement->bindValue(':' . $key, $value);
        }
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return [
            'items' => array_map(
                static fn (array $row): array => [
                    'id' => (int) $row['id'],
                    'actor' => $row['actor_email'],
                    'action' => $row['action'],
                    'entity' => $row['entity'],
                    'entity_id' => (int) $row['entity_id'],
                    'occurred_at' => $row['occurred_at'],
                    'old_values' => $row['old_values'] === null
                        ? null
                        : json_decode($row['old_values'], true, 512, JSON_THROW_ON_ERROR),
                    'new_values' => $row['new_values'] === null
                        ? null
                        : json_decode($row['new_values'], true, 512, JSON_THROW_ON_ERROR),
                ],
                $statement->fetchAll()
            ),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'pages' => $total === 0 ? 0 : (int) ceil($total / $perPage),
        ];
    }
}
