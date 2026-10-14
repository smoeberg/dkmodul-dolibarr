<?php

require_once dirname(__DIR__, 2).'/dkmodul/class/Compliance/DkMigrationDecommissionPolicy.php';

$now = new DateTimeImmutable('2026-10-14T12:00:00Z');
$base = array(
    'schemaVersion' => '1',
    'migrationId' => 'mig-2026-001',
    'sourceSystem' => 'dolibarr-prod-old',
    'destinationSystem' => 'dolibarr-prod-new',
    'exportedAt' => '2026-10-14T08:00:00Z',
    'verifiedAt' => '2026-10-14T09:00:00Z',
    'exportSha256' => str_repeat('a', 64),
    'approvedBy' => 'operator-42',
    'cutoverAt' => '2026-10-14T10:00:00Z',
    'decommissionAfter' => '2026-11-13T00:00:00Z',
);

$result = DkMigrationDecommissionPolicy::validate($base, $now);
if ($result['migration_id'] !== 'mig-2026-001') {
    throw new RuntimeException('Migration evidence was not accepted');
}

$cases = array(
    'same endpoint' => array('sourceSystem' => 'dolibarr-prod-new'),
    'verification before export' => array('verifiedAt' => '2026-10-14T07:00:00Z'),
    'cutover before verification' => array('cutoverAt' => '2026-10-14T08:30:00Z'),
    'decommission before cutover' => array('decommissionAfter' => '2026-10-14T09:30:00Z'),
    'future export' => array('exportedAt' => '2026-10-14T13:00:00Z'),
    'invalid hash' => array('exportSha256' => 'not-a-hash'),
);

foreach ($cases as $name => $change) {
    $evidence = array_merge($base, $change);
    $failed = false;
    try {
        DkMigrationDecommissionPolicy::validate($evidence, $now);
    } catch (InvalidArgumentException $e) {
        $failed = true;
    }
    if (!$failed) {
        throw new RuntimeException('Migration negative case did not fail: '.$name);
    }
}

echo "Migration/decommission policy tests passed\n";
