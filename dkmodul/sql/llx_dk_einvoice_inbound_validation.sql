CREATE TABLE llx_dk_einvoice_inbound_validation (
    rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
    entity INTEGER NOT NULL DEFAULT 1,
    inbound_rowid BIGINT NOT NULL,
    event_type VARCHAR(16) NOT NULL,
    invoice_id VARCHAR(255) NULL,
    invoice_uuid VARCHAR(64) NULL,
    issue_date DATE NULL,
    currency_code VARCHAR(3) NULL,
    supplier_endpoint VARCHAR(128) NULL,
    customer_endpoint VARCHAR(128) NULL,
    payable_amount DECIMAL(24,8) NULL,
    evidence_hash CHAR(64) NOT NULL,
    evidence_json LONGTEXT NOT NULL,
    fk_user_author BIGINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uk_dk_einvoice_inbound_validation (inbound_rowid),
    KEY idx_dk_einvoice_inbound_state (entity, event_type, rowid)
) ENGINE=InnoDB;
