CREATE TABLE llx_dk_saft_import_account (
    rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
    fk_import BIGINT NOT NULL,
    source_account_id VARCHAR(64) NOT NULL,
    description VARCHAR(255) NOT NULL,
    standard_account_id VARCHAR(64) NULL,
    account_type VARCHAR(32) NOT NULL,
    resolved_local_account VARCHAR(64) NULL,
    mapping_status VARCHAR(32) NOT NULL DEFAULT 'unresolved',
    mapping_note VARCHAR(500) NULL,
    opening_debit DECIMAL(24,8) NULL,
    opening_credit DECIMAL(24,8) NULL,
    closing_debit DECIMAL(24,8) NULL,
    closing_credit DECIMAL(24,8) NULL,
    UNIQUE KEY uk_dk_saft_import_account (fk_import, source_account_id),
    KEY idx_dk_saft_import_account_standard (standard_account_id),
    KEY idx_dk_saft_import_account_mapping (fk_import, mapping_status)
) ENGINE=InnoDB;
