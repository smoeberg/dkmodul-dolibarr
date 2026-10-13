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

assert(DkBackupOperationalPolicy::validate($valid, $now, '2031-10-13') === array());

$invalidSchedule = $valid;
$invalidSchedule['full_schedule'] = 'daily:02:00';
$invalidSchedule['incremental_schedule'] = 'hourly:00:00';
$reasons = DkBackupOperationalPolicy::validate($invalidSchedule, $now, '2031-10-13');
assert(in_array('full-backup-schedule-not-weekly', $reasons, true));
assert(in_array('incremental-backup-schedule-not-daily', $reasons, true));

$shortRetention = DkBackupOperationalPolicy::validate($valid, $now, '2031-10-12');
assert(in_array('required-retention-less-than-five-years', $shortRetention, true));

$missing = $valid;
unset($missing['regions']);
$missingReasons = DkBackupOperationalPolicy::validate($missing, $now, '2031-10-13');
assert(in_array('backup-regions-missing', $missingReasons, true));

$throws = false;
try {
    DkBackupOperationalPolicy::validate($valid, $now, 'not-a-date');
} catch (RuntimeException $e) {
    $throws = true;
}
assert($throws === true);

echo "Backup operational policy evidence passed\n";
