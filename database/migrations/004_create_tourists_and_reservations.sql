CREATE TABLE tourists (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(254) NULL,
    phone VARCHAR(40) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY tourists_name_index (full_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE reservations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    departure_id BIGINT UNSIGNED NOT NULL,
    tourist_id BIGINT UNSIGNED NOT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    passenger_count SMALLINT UNSIGNED NOT NULL,
    price_per_person DECIMAL(10, 2) NOT NULL,
    total_price DECIMAL(12, 2) NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'confirmada',
    idempotency_key VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    request_hash CHAR(64) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY reservations_actor_idempotency_unique (created_by, idempotency_key),
    KEY reservations_departure_status_index (departure_id, status),
    KEY reservations_tourist_index (tourist_id),
    CONSTRAINT reservations_departure_fk FOREIGN KEY (departure_id)
        REFERENCES departures (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT reservations_tourist_fk FOREIGN KEY (tourist_id)
        REFERENCES tourists (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT reservations_user_fk FOREIGN KEY (created_by)
        REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT reservations_passenger_count_positive CHECK (passenger_count > 0),
    CONSTRAINT reservations_price_nonnegative CHECK (price_per_person >= 0),
    CONSTRAINT reservations_total_nonnegative CHECK (total_price >= 0),
    CONSTRAINT reservations_status_valid CHECK (status IN ('confirmada', 'cancelada'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE reservation_passengers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reservation_id BIGINT UNSIGNED NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    PRIMARY KEY (id),
    KEY reservation_passengers_reservation_index (reservation_id),
    CONSTRAINT reservation_passengers_reservation_fk FOREIGN KEY (reservation_id)
        REFERENCES reservations (id) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
