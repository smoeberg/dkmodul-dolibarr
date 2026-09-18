CREATE TABLE llx_dk_account_mapping (
    rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
    entity INTEGER NOT NULL DEFAULT 1,
    source_account VARCHAR(32) NOT NULL,
    standard_version VARCHAR(32) NOT NULL,
    standard_account VARCHAR(32) NOT NULL,
    valid_from DATE NOT NULL,
    valid_to DATE NULL,
    fk_user_author INTEGER NOT NULL,
    date_creation DATETIME NOT NULL,
    KEY idx_dk_account_mapping_source (entity, source_account, valid_from),
    KEY idx_dk_account_mapping_target (standard_version, standard_account)
) ENGINE=InnoDB;
