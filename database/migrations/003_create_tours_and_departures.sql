CREATE TABLE tours (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    itinerary TEXT NOT NULL,
    duration_days INT UNSIGNED NOT NULL,
    base_price DECIMAL(10, 2) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY tours_active_name_index (is_active, name),
    CONSTRAINT tours_duration_positive CHECK (duration_days > 0),
    CONSTRAINT tours_price_nonnegative CHECK (base_price >= 0),
    CONSTRAINT tours_active_valid CHECK (is_active IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE departures (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tour_id BIGINT UNSIGNED NOT NULL,
    start_at DATETIME NOT NULL,
    end_at DATETIME NOT NULL,
    capacity INT UNSIGNED NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'programada',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY departures_tour_start_index (tour_id, start_at),
    KEY departures_start_status_index (start_at, status),
    CONSTRAINT departures_tour_fk FOREIGN KEY (tour_id)
        REFERENCES tours (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT departures_time_order CHECK (end_at > start_at),
    CONSTRAINT departures_capacity_positive CHECK (capacity > 0),
    CONSTRAINT departures_status_valid CHECK (
        status IN ('programada', 'en_curso', 'finalizada', 'cancelada')
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
