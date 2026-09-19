ALTER TABLE llx_dk_audit_event
    ADD UNIQUE INDEX IF NOT EXISTS uk_dk_audit_previous_hash (entity, previous_hash);
