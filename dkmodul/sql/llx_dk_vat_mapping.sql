CREATE TABLE llx_dk_vat_mapping (
    rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
    entity INTEGER NOT NULL DEFAULT 1,
    source_tax_code VARCHAR(32) NOT NULL,
    standard_version VARCHAR(32) NOT NULL,
    standard_tax_code VARCHAR(32) NOT NULL,
    valid_from DATE NOT NULL,
    valid_to DATE NULL,
    fk_user_author INTEGER NOT NULL,
    date_creation DATETIME NOT NULL,
    KEY idx_dk_vat_mapping_source (entity, source_tax_code, valid_from),
    KEY idx_dk_vat_mapping_target (standard_version, standard_tax_code)
) ENGINE=InnoDB;
