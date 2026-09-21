<?php

require '/var/www/html/main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/dkmodul/class/Compliance/BackupComplianceMonitor.php';

$monitor = new DkBackupComplianceMonitor($db);
$status = $monitor->status(
    1,
    'integration-test',
    '2031-12-31',
    'DK12345678',
    new DateTimeImmutable('2026-09-21T12:00:00+00:00')
);

if (!$status['compliant']) {
    throw new RuntimeException('Expected compliant backup evidence: '.implode(', ', $status['reasons']));
}

echo "Backup compliance monitor integration test passed\n";
