CREATE TABLE llx_dk_einvoice_delivery (
    rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
    entity INTEGER NOT NULL DEFAULT 1,
    delivery_uuid VARCHAR(36) NOT NULL,
    document_archive_rowid BIGINT NOT NULL,
    transport VARCHAR(32) NOT NULL,
    endpoint_scheme VARCHAR(32) NOT NULL,
    endpoint_id VARCHAR(128) NOT NULL,
    idempotency_key CHAR(64) NOT NULL,
    fk_user_author BIGINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uk_dk_einvoice_delivery_uuid (delivery_uuid),
    UNIQUE KEY uk_dk_einvoice_idempotency (entity, idempotency_key),
    KEY idx_dk_einvoice_document (entity, document_archive_rowid)
) ENGINE=InnoDB;
