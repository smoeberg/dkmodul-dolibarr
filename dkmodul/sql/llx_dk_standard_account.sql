CREATE TABLE llx_dk_standard_account (
    rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
    standard_version VARCHAR(32) NOT NULL,
    valid_from DATE NOT NULL,
    account_code VARCHAR(32) NOT NULL,
    account_type VARCHAR(64) NULL,
    label VARCHAR(500) NOT NULL,
    source_hash CHAR(64) NOT NULL,
    date_imported DATETIME NOT NULL,
    UNIQUE KEY uk_dk_standard_account (standard_version, account_code),
    KEY idx_dk_standard_account_valid_from (valid_from)
) ENGINE=InnoDB;
