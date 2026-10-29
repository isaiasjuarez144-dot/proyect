CREATE TABLE audit_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(32) NOT NULL,
    entity VARCHAR(64) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    old_values JSON NULL,
    new_values JSON NULL,
    PRIMARY KEY (id),
    KEY audit_events_filters (occurred_at, entity, action, actor_id, id),
    CONSTRAINT audit_events_actor_fk FOREIGN KEY (actor_id)
        REFERENCES users (id) ON DELETE CASCADE ON UPDATE RESTRICT,
    CONSTRAINT audit_events_action_valid CHECK (action IN ('created', 'updated', 'cancelled', 'voided')),
    CONSTRAINT audit_events_entity_nonempty CHECK (CHAR_LENGTH(entity) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
