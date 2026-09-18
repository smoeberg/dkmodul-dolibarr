CREATE TABLE llx_dk_standard_vat_code (
    rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
    standard_version VARCHAR(32) NOT NULL,
    valid_from DATE NOT NULL,
    tax_code VARCHAR(32) NOT NULL,
    label VARCHAR(500) NOT NULL,
    tax_percentage DECIMAL(12,6) NULL,
    country_code VARCHAR(2) NOT NULL DEFAULT 'DK',
    source_hash CHAR(64) NOT NULL,
    date_imported DATETIME NOT NULL,
    UNIQUE KEY uk_dk_standard_vat_code (standard_version, tax_code),
    KEY idx_dk_standard_vat_valid_from (valid_from)
) ENGINE=InnoDB;
