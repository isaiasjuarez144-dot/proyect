CREATE TABLE vehicles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    plate VARCHAR(20) NOT NULL,
    capacity INT UNSIGNED NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'operativo',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY vehicles_plate_unique (plate),
    KEY vehicles_status_plate_index (status, plate),
    CONSTRAINT vehicles_capacity_positive CHECK (capacity > 0),
    CONSTRAINT vehicles_status_valid CHECK (status IN ('operativo', 'inactivo'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE vehicle_maintenance (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    vehicle_id BIGINT UNSIGNED NOT NULL,
    start_at DATETIME NOT NULL,
    end_at DATETIME NOT NULL,
    description VARCHAR(1000) NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'programado',
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY vehicle_maintenance_vehicle_time_index (vehicle_id, start_at, end_at, status),
    CONSTRAINT vehicle_maintenance_vehicle_fk FOREIGN KEY (vehicle_id)
        REFERENCES vehicles (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT vehicle_maintenance_user_fk FOREIGN KEY (created_by)
        REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT vehicle_maintenance_time_order CHECK (end_at > start_at),
    CONSTRAINT vehicle_maintenance_description_nonempty CHECK (CHAR_LENGTH(description) > 0),
    CONSTRAINT vehicle_maintenance_status_valid CHECK (
        status IN ('programado', 'en_curso', 'completado', 'cancelado')
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE departure_assignments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    departure_id BIGINT UNSIGNED NOT NULL,
    vehicle_id BIGINT UNSIGNED NOT NULL,
    driver_user_id BIGINT UNSIGNED NOT NULL,
    assigned_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY departure_assignments_departure_unique (departure_id),
    KEY departure_assignments_vehicle_index (vehicle_id),
    KEY departure_assignments_driver_index (driver_user_id),
    CONSTRAINT departure_assignments_departure_fk FOREIGN KEY (departure_id)
        REFERENCES departures (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT departure_assignments_vehicle_fk FOREIGN KEY (vehicle_id)
        REFERENCES vehicles (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT departure_assignments_driver_fk FOREIGN KEY (driver_user_id)
        REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT departure_assignments_actor_fk FOREIGN KEY (assigned_by)
        REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE departure_incidents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    departure_id BIGINT UNSIGNED NOT NULL,
    reported_by BIGINT UNSIGNED NOT NULL,
    occurred_at DATETIME NOT NULL,
    description VARCHAR(2000) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY departure_incidents_departure_time_index (departure_id, occurred_at),
    CONSTRAINT departure_incidents_departure_fk FOREIGN KEY (departure_id)
        REFERENCES departures (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT departure_incidents_user_fk FOREIGN KEY (reported_by)
        REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT departure_incidents_description_nonempty CHECK (CHAR_LENGTH(description) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
