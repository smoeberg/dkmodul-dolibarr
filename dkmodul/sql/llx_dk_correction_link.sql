CREATE TABLE llx_dk_correction_link (
    rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
    entity INTEGER NOT NULL DEFAULT 1,
    original_piece_num BIGINT NOT NULL,
    reversal_piece_num BIGINT NOT NULL,
    replacement_piece_num BIGINT NULL,
    reason VARCHAR(500) NOT NULL,
    fk_user_author BIGINT NOT NULL,
    date_creation DATETIME NOT NULL,
    UNIQUE KEY uk_dk_correction_reversal (entity, reversal_piece_num),
    KEY idx_dk_correction_original (entity, original_piece_num),
    KEY idx_dk_correction_replacement (entity, replacement_piece_num)
) ENGINE=InnoDB;
