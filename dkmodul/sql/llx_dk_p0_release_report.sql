CREATE TABLE llx_dk_p0_release_report (
    rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
    entity INTEGER NOT NULL DEFAULT 1,
    report_uuid VARCHAR(36) NOT NULL,
    deployment_id VARCHAR(128) NOT NULL,
    product_release VARCHAR(64) NOT NULL,
    decision VARCHAR(16) NOT NULL,
    report_sha256 CHAR(64) NOT NULL,
    report_json LONGTEXT NOT NULL,
    generated_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uk_dk_p0_release_report_uuid (report_uuid),
    UNIQUE KEY uk_dk_p0_release_report_hash (entity, deployment_id, report_sha256),
    KEY idx_dk_p0_release_report_latest (entity, deployment_id, generated_at)
) ENGINE=InnoDB;
