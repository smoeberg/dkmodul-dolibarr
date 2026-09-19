CREATE TABLE llx_dk_einvoice_transport_event (
    rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
    entity INTEGER NOT NULL DEFAULT 1,
    delivery_rowid BIGINT NOT NULL,
    event_type VARCHAR(16) NOT NULL,
    attempt_number INTEGER NOT NULL,
    provider_message_id VARCHAR(255) NULL,
    receipt_code VARCHAR(64) NULL,
    receipt_at DATETIME NULL,
    receipt_hash CHAR(64) NOT NULL,
    receipt_json LONGTEXT NOT NULL,
    fk_user_author BIGINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uk_dk_einvoice_event_attempt (delivery_rowid, attempt_number, event_type),
    KEY idx_dk_einvoice_event_delivery (entity, delivery_rowid, rowid)
) ENGINE=InnoDB;
