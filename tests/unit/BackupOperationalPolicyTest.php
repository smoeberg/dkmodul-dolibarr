<?php

require_once __DIR__.'/../../dkmodul/class/Compliance/BackupOperationalPolicy.php';

$now = new DateTimeImmutable('2026-10-13T12:00:00Z');

$valid = array(
    'legal_name' => 'Independent Backup Provider',
    'registration_number' => 'DK12345678',
    'country' => 'DK',
    'regions' => array('DK'),
    'full_schedule' => 'weekly:sunday:02:00',
    'incremental_schedule' => 'daily:02:00,14:00',
);

$evidence = array(
    'quarterly_restore_test' => array(
        'status' => 'passed',
        'completed_at' => '2026-10-13T10:00:00Z',
        'backup_evidence_uuid' => 'backup-evidence-2026-q4',
        'reviewed_by' => 'approved reviewer',
        'reviewed_at' => '2026-10-13T11:00:00Z',
        'evidence_reference' => 'evidence/restore/2026-q4.json',
    ),
    'customer_exit_access_test' => array(
        'status' => 'passed',
        'tested_at' => '2026-10-01T10:00:00Z',
        'access_reference' => 'evidence/customer-exit/2026-10-01/export.zip',
        'export_sha256' => hash('sha256', 'customer-exit-export'),
        'reviewed_by' => 'approved reviewer',
        'reviewed_at' => '2026-10-01T11:00:00Z',
    ),
);

assert(DkBackupOperationalPolicy::validate($valid, $now, '2031-10-13', $evidence) === array());

$invalidSchedule = $valid;
$invalidSchedule['full_schedule'] = 'daily:02:00';
$invalidSchedule['incremental_schedule'] = 'hourly:00:00';
$reasons = DkBackupOperationalPolicy::validate($invalidSchedule, $now, '2031-10-13');
assert(in_array('full-backup-schedule-not-weekly', $reasons, true));
assert(in_array('incremental-backup-schedule-not-daily', $reasons, true));

$malformedSchedules = array(
    'weekly:garbage:02:00',
    'weekly:sunday:24:00',
    'weekly:sunday:2:00',
    'daily:02:00,garbage',
    'daily:2:00',
    'daily:25:00',
);
foreach ($malformedSchedules as $schedule) {
    $candidate = $valid;
    $candidate['full_schedule'] = $schedule;
    $reasons = DkBackupOperationalPolicy::validate($candidate, $now, '2031-10-13', $evidence);
    assert(in_array('full-backup-schedule-not-weekly', $reasons, true));
}

$malformedIncrementals = array(
    'hourly:02:00',
    'daily:02:00,25:00',
    'daily:02:00,garbage',
);
foreach ($malformedIncrementals as $schedule) {
    $candidate = $valid;
    $candidate['incremental_schedule'] = $schedule;
    $reasons = DkBackupOperationalPolicy::validate($candidate, $now, '2031-10-13', $evidence);
    assert(in_array('incremental-backup-schedule-not-daily', $reasons, true));
}


$shortRetention = DkBackupOperationalPolicy::validate($valid, $now, '2031-10-12', $evidence);
assert(in_array('required-retention-less-than-five-years', $shortRetention, true));

$missing = $valid;
unset($missing['regions']);
$missingReasons = DkBackupOperationalPolicy::validate($missing, $now, '2031-10-13', $evidence);
assert(in_array('backup-regions-missing', $missingReasons, true));


$missingEvidence = DkBackupOperationalPolicy::validate($valid, $now, '2031-10-13');
assert(in_array('backup-evidence-attestation-missing', $missingEvidence, true));
assert(in_array('quarterly-restore-evidence-missing', $missingEvidence, true));
assert(in_array('customer-exit-access-evidence-missing', $missingEvidence, true));

$staleRestore = $evidence;
$staleRestore['quarterly_restore_test']['completed_at'] = '2026-06-01T10:00:00Z';
$staleReasons = DkBackupOperationalPolicy::validate($valid, $now, '2031-10-13', $staleRestore);
assert(in_array('quarterly-restore-evidence-stale-or-future', $staleReasons, true));

$failedExit = $evidence;
$failedExit['customer_exit_access_test']['status'] = 'failed';
$failedExit['customer_exit_access_test']['export_sha256'] = 'invalid';
$exitReasons = DkBackupOperationalPolicy::validate($valid, $now, '2031-10-13', $failedExit);
assert(in_array('customer-exit-access-evidence-not-passed', $exitReasons, true));
assert(in_array('customer-exit-access-evidence-invalid-export-hash', $exitReasons, true));

$throws = false;
try {
    DkBackupOperationalPolicy::validate($valid, $now, 'not-a-date', $evidence);
} catch (RuntimeException $e) {
    $throws = true;
}
assert($throws === true);

echo "Backup operational policy evidence passed\n";
