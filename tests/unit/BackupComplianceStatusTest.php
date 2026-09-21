<?php

require_once __DIR__.'/../../dkmodul/class/Compliance/BackupComplianceStatus.php';

$now = new DateTimeImmutable('2026-09-21T12:00:00+00:00');
$hash = str_repeat('a', 64);
$baseBackup = array(
    'status' => 'success',
    'completed_at' => '2026-09-21T01:00:00+00:00',
    'country' => 'DK',
    'provider_registration' => 'DK-BACKUP-1',
    'provider_name' => 'Independent Backup ApS',
    'region' => 'dk-west-1',
    'object_reference' => 'backup/object',
    'byte_size' => 1024,
    'signature_verified' => 1,
    'retention_until' => '2032-12-31',
    'immutable_until' => '2032-12-31',
    'receipt_sha256' => $hash,
);
$full = $baseBackup;
$full['backup_type'] = 'full';
$full['completed_at'] = '2026-09-18T01:00:00+00:00';
$incremental = $baseBackup;
$incremental['backup_type'] = 'incremental';

$restore = array(
    'status' => 'passed',
    'completed_at' => '2026-09-01T12:00:00+00:00',
    'database_sha256' => $hash,
    'document_sample_sha256' => $hash,
    'saft_sha256' => $hash,
    'debit_total' => '1250.00000000',
    'credit_total' => '1250.00',
    'reviewed_by' => 'Independent reviewer',
    'reviewed_at' => '2026-09-02T12:00:00+00:00',
    'evidence_reference' => 'evidence/restore-2026-q3.pdf',
);

$status = DkBackupComplianceStatus::evaluate(
    array($full, $incremental),
    array($restore),
    $now,
    '2031-12-31',
    'DK-HOST-1'
);
assert($status['compliant'] === true);
assert($status['reasons'] === array());

$outsideEea = $incremental;
$outsideEea['country'] = 'US';
$staleFull = $full;
$staleFull['completed_at'] = '2026-09-01T01:00:00+00:00';
$badRestore = $restore;
$badRestore['debit_total'] = '1250.00';
$badRestore['credit_total'] = '1249.99';
$blocked = DkBackupComplianceStatus::evaluate(
    array($staleFull, $outsideEea),
    array($badRestore),
    $now,
    '2031-12-31',
    'DK-HOST-1'
);
assert($blocked['compliant'] === false);
assert(in_array('full-backup-stale', $blocked['reasons'], true));
assert(in_array('incremental-backup-outside-eu-eea', $blocked['reasons'], true));
assert(in_array('restore-bookkeeping-unbalanced', $blocked['reasons'], true));

$missing = DkBackupComplianceStatus::evaluate(array(), array(), $now, '2031-12-31', 'DK-HOST-1');
assert($missing['compliant'] === false);
assert(in_array('missing-successful-full-backup', $missing['reasons'], true));
assert(in_array('missing-successful-incremental-backup', $missing['reasons'], true));
assert(in_array('missing-passed-restore-test', $missing['reasons'], true));

echo "Backup compliance status tests passed\n";
