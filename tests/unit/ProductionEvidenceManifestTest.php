<?php

require_once dirname(__DIR__, 2).'/dkmodul/class/Compliance/ProductionEvidenceManifest.php';

$base = array(
    'schemaVersion' => '1',
    'deploymentId' => 'deployment-001',
    'items' => array(
        array(
            'controlId' => 'production-release-signing',
            'status' => 'evidenced',
            'evidenceRef' => 'evidence/key-ceremony-001.json',
            'evidenceSha256' => str_repeat('a', 64),
            'approvedBy' => 'reviewer-1',
            'approvedAt' => '2026-10-14T10:00:00Z',
        ),
    ),
);

$complete = $base;
foreach (DkProductionEvidenceManifest::REQUIRED_CONTROLS as $controlId) {
    if ($controlId === 'production-release-signing') continue;
    $complete['items'][] = array(
        'controlId' => $controlId,
        'status' => 'evidenced',
        'evidenceRef' => 'evidence/'.$controlId.'.json',
        'evidenceSha256' => str_repeat('b', 64),
        'approvedBy' => 'reviewer-1',
        'approvedAt' => '2026-10-14T10:00:00Z',
    );
}

$now = new DateTimeImmutable('2026-10-15T08:00:00Z');
$result = DkProductionEvidenceManifest::assertComplete($complete, $now);
if ($result['open_count'] !== 0 || $result['evidenced_count'] !== count(DkProductionEvidenceManifest::REQUIRED_CONTROLS)) {
    throw new RuntimeException('Complete production evidence manifest was rejected');
}

$open = $base;
$open['items'][] = array('controlId' => 'quarterly-restore', 'status' => 'open');
$failed = false;
try {
    DkProductionEvidenceManifest::assertComplete($open);
} catch (Throwable $e) {
    $failed = true;
}
if (!$failed) {
    throw new RuntimeException('Incomplete production evidence manifest was accepted');
}

$missingHash = $base;
$missingHash['items'][0]['evidenceSha256'] = 'bad';
$failed = false;
try {
    DkProductionEvidenceManifest::validate($missingHash);
} catch (InvalidArgumentException $e) {
    $failed = true;
}
if (!$failed) {
    throw new RuntimeException('Evidenced item without valid SHA-256 was accepted');
}

echo "Production evidence manifest tests passed\n";

$invalidTimestamp = $base;
$invalidTimestamp['items'][0]['approvedAt'] = '2026-02-30T10:00:00Z';
$failed = false;
try {
    DkProductionEvidenceManifest::validate($invalidTimestamp);
} catch (InvalidArgumentException $e) {
    $failed = true;
}
if (!$failed) {
    throw new RuntimeException('Invalid UTC approval timestamp was accepted');
}

$localTimestamp = $base;
$localTimestamp['items'][0]['approvedAt'] = '2026-10-14T10:00:00+02:00';
$failed = false;
try {
    DkProductionEvidenceManifest::validate($localTimestamp);
} catch (InvalidArgumentException $e) {
    $failed = true;
}
if (!$failed) {
    throw new RuntimeException('Non-UTC approval timestamp was accepted');
}

$missingRequired = $base;
$failed = false;
try {
    DkProductionEvidenceManifest::assertComplete($missingRequired);
} catch (InvalidArgumentException $e) {
    $failed = true;
}
if (!$failed) {
    throw new RuntimeException('Manifest missing required production controls was accepted');
}

$futureApproval = $base;
$futureApproval['items'][0]['approvedAt'] = '2026-10-15T09:00:00Z';
$failed = false;
try {
    DkProductionEvidenceManifest::validate($futureApproval, $now);
} catch (InvalidArgumentException $e) {
    $failed = true;
}
if (!$failed) {
    throw new RuntimeException('Future production evidence approval was accepted');
}

$unknownControl = $complete;
$unknownControl['items'][] = array(
    'controlId' => 'unknown-control',
    'status' => 'evidenced',
    'evidenceRef' => 'evidence/unknown-control.json',
    'evidenceSha256' => str_repeat('c', 64),
    'approvedBy' => 'reviewer-1',
    'approvedAt' => '2026-10-14T10:00:00Z',
);
$failed = false;
try {
    DkProductionEvidenceManifest::validate($unknownControl, $now);
} catch (InvalidArgumentException $e) {
    $failed = true;
}
if (!$failed) {
    throw new RuntimeException('Unknown production evidence control was accepted');
}
