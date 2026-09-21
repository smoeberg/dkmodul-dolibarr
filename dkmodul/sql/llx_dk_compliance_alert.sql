CREATE TABLE llx_dk_compliance_alert (
    rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
    entity INTEGER NOT NULL DEFAULT 1,
    alert_uuid VARCHAR(36) NOT NULL,
    deployment_id VARCHAR(128) NOT NULL,
    check_type VARCHAR(64) NOT NULL,
    fingerprint CHAR(64) NOT NULL,
    event_type VARCHAR(16) NOT NULL,
    severity VARCHAR(16) NOT NULL,
    reason_codes TEXT NOT NULL,
    check_uuid VARCHAR(36) NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uk_dk_compliance_alert_uuid (alert_uuid),
    KEY idx_dk_compliance_alert_state (entity, deployment_id, fingerprint, created_at)
) ENGINE=InnoDB;
