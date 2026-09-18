CREATE TABLE llx_dk_saft_import_transaction (
    rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
    fk_import BIGINT NOT NULL,
    external_transaction_id VARCHAR(128) NOT NULL,
    journal_id VARCHAR(64) NOT NULL,
    journal_description VARCHAR(255) NOT NULL,
    transaction_date DATE NOT NULL,
    registration_datetime DATETIME NOT NULL,
    actor VARCHAR(255) NOT NULL,
    description VARCHAR(500) NULL,
    local_piece_num INTEGER NULL,
    local_ref VARCHAR(30) NULL,
    UNIQUE KEY uk_dk_saft_import_transaction (fk_import, external_transaction_id),
    KEY idx_dk_saft_import_transaction_date (fk_import, transaction_date),
    KEY idx_dk_saft_import_transaction_piece (local_piece_num)
) ENGINE=InnoDB;
