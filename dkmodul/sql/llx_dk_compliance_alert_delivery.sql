CREATE TABLE llx_dk_compliance_alert_delivery (
    rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
    entity INTEGER NOT NULL DEFAULT 1,
    delivery_uuid VARCHAR(36) NOT NULL,
    alert_uuid VARCHAR(36) NOT NULL,
    channel VARCHAR(32) NOT NULL,
    status VARCHAR(16) NOT NULL,
    external_reference VARCHAR(255) NOT NULL,
    attempted_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uk_dk_compliance_delivery_uuid (delivery_uuid),
    KEY idx_dk_compliance_delivery_alert (entity, alert_uuid, attempted_at)
) ENGINE=InnoDB;
