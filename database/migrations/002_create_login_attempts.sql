CREATE TABLE login_attempts (
    identifier_hash CHAR(64) NOT NULL,
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    first_attempt_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    locked_until TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (identifier_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
