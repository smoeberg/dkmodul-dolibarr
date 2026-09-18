CREATE TABLE llx_dk_correction (
    rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
    entity INTEGER NOT NULL DEFAULT 1,
    original_piece_num INTEGER NOT NULL,
    correction_piece_num INTEGER NOT NULL,
    relation_type VARCHAR(32) NOT NULL,
    reason VARCHAR(500) NOT NULL,
    fk_user_author INTEGER NOT NULL,
    date_creation DATETIME NOT NULL,
    UNIQUE KEY uk_dk_correction_target (entity, correction_piece_num),
    KEY idx_dk_correction_original (entity, original_piece_num)
) ENGINE=InnoDB;
