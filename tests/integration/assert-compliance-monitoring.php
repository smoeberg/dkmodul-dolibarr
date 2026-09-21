<?php

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);

require '/var/www/html/main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/dkmodul/class/Compliance/ComplianceMonitorRepository.php';
require_once DOL_DOCUMENT_ROOT.'/custom/dkmodul/class/Compliance/ComplianceMonitoringService.php';
require_once DOL_DOCUMENT_ROOT.'/custom/dkmodul/class/Compliance/OutboxAlertTransport.php';

$service = new DkComplianceMonitoringService(
    new DkComplianceMonitorRepository($db),
    new DkOutboxAlertTransport()
);
$checkedAt = new DateTimeImmutable('2026-09-21T12:00:00+00:00');
$nextDueAt = $checkedAt->modify('+1 hour');
$hash = str_repeat('f', 64);

$service->record(1, 'monitor-integration', 'backup-restore', 'failed', array('full-backup-stale'), $hash, $checkedAt, $nextDueAt);
$service->record(1, 'monitor-integration', 'backup-restore', 'failed', array('full-backup-stale'), $hash, $checkedAt, $nextDueAt);
$service->record(1, 'monitor-integration', 'backup-restore', 'ok', array(), $hash, $checkedAt, $nextDueAt);

$checks = $db->query("SELECT COUNT(*) AS count_rows FROM ".$db->prefix()."dk_compliance_check WHERE deployment_id='monitor-integration'");
$alerts = $db->query("SELECT COUNT(*) AS count_rows FROM ".$db->prefix()."dk_compliance_alert WHERE deployment_id='monitor-integration'");
$deliveries = $db->query("SELECT COUNT(*) AS count_rows FROM ".$db->prefix()."dk_compliance_alert_delivery d JOIN ".$db->prefix()."dk_compliance_alert a ON a.alert_uuid=d.alert_uuid WHERE a.deployment_id='monitor-integration'");
if ((int) $db->fetch_object($checks)->count_rows !== 3
    || (int) $db->fetch_object($alerts)->count_rows !== 2
    || (int) $db->fetch_object($deliveries)->count_rows !== 2) {
    throw new RuntimeException('Compliance monitoring deduplication or recovery evidence failed');
}

echo "Compliance monitoring integration test passed\n";
