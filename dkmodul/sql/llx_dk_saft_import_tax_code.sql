CREATE TABLE llx_dk_saft_import_tax_code (
    rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
    fk_import BIGINT NOT NULL,
    tax_type VARCHAR(32) NOT NULL,
    source_tax_code VARCHAR(64) NOT NULL,
    standard_tax_code VARCHAR(64) NULL,
    effective_date DATE NOT NULL,
    expiration_date DATE NULL,
    description VARCHAR(500) NOT NULL,
    tax_percentage DECIMAL(12,6) NULL,
    country_code VARCHAR(2) NULL,
    UNIQUE KEY uk_dk_saft_import_tax_code (fk_import, tax_type, source_tax_code),
    KEY idx_dk_saft_import_tax_standard (fk_import, standard_tax_code)
) ENGINE=InnoDB;
