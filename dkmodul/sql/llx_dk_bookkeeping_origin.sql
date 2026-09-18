CREATE TABLE llx_dk_bookkeeping_origin (
    rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
    entity INTEGER NOT NULL DEFAULT 1,
    bookkeeping_rowid BIGINT NOT NULL,
    piece_num INTEGER NOT NULL,
    fk_user_author INTEGER NOT NULL DEFAULT 0,
    database_user VARCHAR(255) NOT NULL,
    origin_type VARCHAR(32) NOT NULL,
    recorded_at DATETIME NOT NULL,
    content_hash CHAR(64) NOT NULL,
    UNIQUE KEY uk_dk_bookkeeping_origin_row (bookkeeping_rowid),
    KEY idx_dk_bookkeeping_origin_piece (entity, piece_num)
) ENGINE=InnoDB;
