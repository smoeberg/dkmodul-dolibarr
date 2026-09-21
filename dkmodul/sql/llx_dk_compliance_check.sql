CREATE TABLE llx_dk_compliance_check (
    rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
    entity INTEGER NOT NULL DEFAULT 1,
    check_uuid VARCHAR(36) NOT NULL,
    deployment_id VARCHAR(128) NOT NULL,
    check_type VARCHAR(64) NOT NULL,
    status VARCHAR(16) NOT NULL,
    reason_codes TEXT NOT NULL,
    evidence_sha256 CHAR(64) NOT NULL,
    checked_at DATETIME NOT NULL,
    next_due_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uk_dk_compliance_check_uuid (check_uuid),
    KEY idx_dk_compliance_check_latest (entity, deployment_id, check_type, checked_at)
) ENGINE=InnoDB;
