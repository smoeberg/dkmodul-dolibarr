CREATE TABLE llx_dk_audit_event (
    rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
    entity INTEGER NOT NULL DEFAULT 1,
    event_uuid VARCHAR(36) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    object_type VARCHAR(100) NOT NULL,
    object_id BIGINT NOT NULL,
    actor_id BIGINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    previous_hash CHAR(64) NOT NULL,
    payload_hash CHAR(64) NOT NULL,
    event_hash CHAR(64) NOT NULL,
    payload_json LONGTEXT NULL,
    metadata_json LONGTEXT NULL,
    UNIQUE KEY uk_dk_audit_event_uuid (event_uuid),
    KEY idx_dk_audit_event_entity_rowid (entity, rowid),
    KEY idx_dk_audit_event_object (object_type, object_id)
) ENGINE=InnoDB;
