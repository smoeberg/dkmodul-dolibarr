CREATE TABLE llx_dk_saft_import_line (
    rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
    fk_import_transaction BIGINT NOT NULL,
    external_record_id VARCHAR(128) NOT NULL,
    source_account_id VARCHAR(64) NOT NULL,
    debit DECIMAL(24,8) NOT NULL DEFAULT 0,
    credit DECIMAL(24,8) NOT NULL DEFAULT 0,
    currency_code VARCHAR(3) NULL,
    currency_amount DECIMAL(24,8) NULL,
    description VARCHAR(500) NULL,
    source_document_ref VARCHAR(300) NULL,
    tax_information_json LONGTEXT NULL,
    bookkeeping_rowid BIGINT NULL,
    UNIQUE KEY uk_dk_saft_import_line (fk_import_transaction, external_record_id),
    KEY idx_dk_saft_import_line_account (source_account_id),
    UNIQUE KEY uk_dk_saft_import_line_bookkeeping (bookkeeping_rowid)
) ENGINE=InnoDB;
