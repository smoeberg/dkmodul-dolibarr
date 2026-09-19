CREATE TABLE llx_dk_einvoice_inbound_draft (
    rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
    entity INTEGER NOT NULL DEFAULT 1,
    inbound_rowid BIGINT NOT NULL,
    supplier_rowid BIGINT NOT NULL,
    supplier_invoice_rowid BIGINT NOT NULL,
    supplier_invoice_ref VARCHAR(255) NOT NULL,
    source_content_hash CHAR(64) NOT NULL,
    fk_user_approver BIGINT NOT NULL,
    approved_at DATETIME NOT NULL,
    UNIQUE KEY uk_dk_inbound_draft_source (entity, inbound_rowid),
    UNIQUE KEY uk_dk_inbound_draft_invoice (entity, supplier_invoice_rowid),
    KEY idx_dk_inbound_draft_supplier (entity, supplier_rowid)
) ENGINE=InnoDB;
