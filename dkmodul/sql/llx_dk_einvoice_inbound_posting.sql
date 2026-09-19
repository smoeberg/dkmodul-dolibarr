CREATE TABLE llx_dk_einvoice_inbound_posting (
    rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
    entity INTEGER NOT NULL DEFAULT 1,
    supplier_validation_rowid BIGINT NOT NULL,
    supplier_invoice_rowid BIGINT NOT NULL,
    journal_rowid BIGINT NOT NULL,
    piece_num BIGINT NOT NULL,
    line_count INTEGER NOT NULL,
    debit_total DECIMAL(24,8) NOT NULL,
    credit_total DECIMAL(24,8) NOT NULL,
    fk_user_poster BIGINT NOT NULL,
    posted_at DATETIME NOT NULL,
    UNIQUE KEY uk_dk_inbound_posting_validation (entity, supplier_validation_rowid),
    UNIQUE KEY uk_dk_inbound_posting_invoice (entity, supplier_invoice_rowid),
    KEY idx_dk_inbound_posting_piece (entity, piece_num)
) ENGINE=InnoDB;
