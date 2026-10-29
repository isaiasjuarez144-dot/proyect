CREATE TABLE payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reservation_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12, 2) NOT NULL,
    paid_at DATETIME NOT NULL,
    method VARCHAR(24) NOT NULL,
    reference VARCHAR(120) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    idempotency_key VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    request_hash CHAR(64) NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'aplicado',
    voided_at DATETIME NULL,
    voided_by BIGINT UNSIGNED NULL,
    void_reason VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY payments_actor_idempotency_unique (created_by, idempotency_key),
    KEY payments_reservation_status_index (reservation_id, status),
    CONSTRAINT payments_reservation_fk FOREIGN KEY (reservation_id)
        REFERENCES reservations (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT payments_created_by_fk FOREIGN KEY (created_by)
        REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT payments_voided_by_fk FOREIGN KEY (voided_by)
        REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT payments_amount_positive CHECK (amount > 0),
    CONSTRAINT payments_method_valid CHECK (method IN ('efectivo', 'transferencia', 'qr', 'tarjeta', 'otro')),
    CONSTRAINT payments_status_valid CHECK (status IN ('aplicado', 'anulado'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
