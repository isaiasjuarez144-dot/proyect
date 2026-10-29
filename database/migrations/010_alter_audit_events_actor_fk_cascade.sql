ALTER TABLE audit_events DROP FOREIGN KEY audit_events_actor_fk;
ALTER TABLE audit_events
    ADD CONSTRAINT audit_events_actor_fk
        FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT;
