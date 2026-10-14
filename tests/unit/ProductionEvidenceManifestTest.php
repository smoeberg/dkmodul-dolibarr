<?php

require_once dirname(__DIR__, 2).'/dkmodul/class/Compliance/ProductionEvidenceManifest.php';

$base = array(
    'schemaVersion' => '1',
    'deploymentId' => 'deployment-001',
    'items' => array(
        array(
            'controlId' => 'production-signing-key-ceremony',
            'status' => 'evidenced',
            'evidenceRef' => 'evidence/key-ceremony-001.json',
            'evidenceSha256' => str_repeat('a', 64),
            'approvedBy' => 'reviewer-1',
            'approvedAt' => '2026-10-14T10:00:00Z',
        ),
    ),
);

$result = DkProductionEvidenceManifest::assertComplete($base);
if ($result['open_count'] !== 0 || $result['evidenced_count'] !== 1) {
    throw new RuntimeException('Complete production evidence manifest was rejected');
}

$open = $base;
$open['items'][] = array('controlId' => 'quarterly-restore', 'status' => 'open');
$failed = false;
try {
    DkProductionEvidenceManifest::assertComplete($open);
} catch (RuntimeException $e) {
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
