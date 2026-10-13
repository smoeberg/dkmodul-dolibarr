<?php

require_once __DIR__.'/../../dkmodul/class/Operations/DkBackupEvidenceService.php';

$root = sys_get_temp_dir().'/dk-backup-evidence-'.bin2hex(random_bytes(4));
$service = new DkBackupEvidenceService($root);

$result = $service->record(
    'backup-2026-10-13T090000Z',
    'dolibarr-database-and-document-archive',
    123456,
    hash('sha256', 'backup-artifact'),
    '2026-10-13T09:00:00Z',
    'restore-test-2026-10-13-01',
    'passed',
    42
);

assert(is_file($result['path']));
assert($service->verify($result['path']) === true);
assert($result['record']['restoreStatus'] === 'passed');
assert($result['record']['artifactBytes'] === 123456);

$original = file_get_contents($result['path']);
assert(is_string($original));
file_put_contents($result['path'], str_replace('123456', '123457', $original));
assert($service->verify($result['path']) === false);

$throws = false;
try {
    $service->record(
        'backup-invalid',
        'scope',
        1,
        'not-a-sha256',
        '2026-10-13T09:00:00Z',
        'restore-test-invalid',
        'passed',
        42
    );
} catch (InvalidArgumentException $e) {
    $throws = true;
}
assert($throws === true);

echo "Backup and restore evidence service evidence passed\n";
