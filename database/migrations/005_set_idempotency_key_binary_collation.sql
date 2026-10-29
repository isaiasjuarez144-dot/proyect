ALTER TABLE reservations
    MODIFY idempotency_key VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL;
