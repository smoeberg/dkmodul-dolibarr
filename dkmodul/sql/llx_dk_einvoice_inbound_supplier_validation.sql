CREATE TABLE llx_dk_einvoice_inbound_supplier_validation (
    rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
    entity INTEGER NOT NULL DEFAULT 1,
    inbound_draft_rowid BIGINT NOT NULL,
    supplier_invoice_rowid BIGINT NOT NULL,
    supplier_invoice_ref VARCHAR(255) NOT NULL,
    source_content_hash CHAR(64) NOT NULL,
    fk_user_validator BIGINT NOT NULL,
    validated_at DATETIME NOT NULL,
    UNIQUE KEY uk_dk_inbound_supplier_validation_draft (entity, inbound_draft_rowid),
    UNIQUE KEY uk_dk_inbound_supplier_validation_invoice (entity, supplier_invoice_rowid)
) ENGINE=InnoDB;
