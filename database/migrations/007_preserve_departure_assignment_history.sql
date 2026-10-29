CREATE TABLE departure_assignment_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    departure_id BIGINT UNSIGNED NOT NULL,
    vehicle_id BIGINT UNSIGNED NOT NULL,
    driver_user_id BIGINT UNSIGNED NOT NULL,
    assigned_by BIGINT UNSIGNED NOT NULL,
    replaced_by BIGINT UNSIGNED NOT NULL,
    assigned_at TIMESTAMP NOT NULL,
    replaced_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY departure_assignment_history_departure_index (departure_id, replaced_at),
    CONSTRAINT departure_assignment_history_departure_fk FOREIGN KEY (departure_id)
        REFERENCES departures (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT departure_assignment_history_vehicle_fk FOREIGN KEY (vehicle_id)
        REFERENCES vehicles (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT departure_assignment_history_driver_fk FOREIGN KEY (driver_user_id)
        REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT departure_assignment_history_assigned_by_fk FOREIGN KEY (assigned_by)
        REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT departure_assignment_history_replaced_by_fk FOREIGN KEY (replaced_by)
        REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
