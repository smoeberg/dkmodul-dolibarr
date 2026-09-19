CREATE TABLE llx_dk_einvoice_inbound (
    rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
    entity INTEGER NOT NULL DEFAULT 1,
    inbound_uuid VARCHAR(36) NOT NULL,
    channel VARCHAR(32) NOT NULL,
    provider_message_id VARCHAR(255) NOT NULL,
    sender_endpoint_scheme VARCHAR(32) NOT NULL,
    sender_endpoint_id VARCHAR(128) NOT NULL,
    message_key CHAR(64) NOT NULL,
    storage_key VARCHAR(500) NOT NULL,
    content_hash CHAR(64) NOT NULL,
    byte_size BIGINT NOT NULL,
    fk_user_author BIGINT NOT NULL DEFAULT 0,
    received_at DATETIME NOT NULL,
    UNIQUE KEY uk_dk_einvoice_inbound_uuid (inbound_uuid),
    UNIQUE KEY uk_dk_einvoice_inbound_message (entity, message_key),
    KEY idx_dk_einvoice_inbound_hash (entity, content_hash)
) ENGINE=InnoDB;
