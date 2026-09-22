<?php

require_once __DIR__.'/../../dkmodul/class/Compliance/P0ReleaseGate.php';

$hash = str_repeat('a', 64);
$pass = static function ($gateId) use ($hash) {
    return array('gate_id' => $gateId, 'status' => 'PASS', 'evidence_sha256' => $hash, 'reason_codes' => array());
};
$all = array_map($pass, array(
    'product-manifest', 'deployment-attestation', 'backup-retention-restore',
    'compliance-monitoring', 'access-point-certification', 'security-risk-evidence',
));
$at = new DateTimeImmutable('2026-09-22T12:00:00+00:00');
$report = DkP0ReleaseGate::evaluate('dolibarr-dk', '1.0.0', 'prod-1', array_reverse($all), $at);
assert($report['decision'] === 'PASS');
assert(count($report['gates']) === 6);
assert($report['gates'][0]['gate_id'] === 'product-manifest');
assert(preg_match('/^[a-f0-9]{64}$/', $report['report_sha256']) === 1);

$same = DkP0ReleaseGate::evaluate('dolibarr-dk', '1.0.0', 'prod-1', $all, $at);
assert($same['report_sha256'] === $report['report_sha256']);

$missing = DkP0ReleaseGate::evaluate('dolibarr-dk', '1.0.0', 'prod-1', array_slice($all, 0, 5), $at);
assert($missing['decision'] === 'BLOCKED');
assert($missing['gates'][5]['reason_codes'] === array('missing-gate-result'));

$failed = $all;
$failed[2] = array('gate_id' => 'backup-retention-restore', 'status' => 'FAIL', 'evidence_sha256' => $hash, 'reason_codes' => array('restore-test-stale'));
assert(DkP0ReleaseGate::evaluate('dolibarr-dk', '1.0.0', 'prod-1', $failed, $at)['decision'] === 'FAIL');

$invalidEvidenceBlocked = false;
try {
    $invalid = $all;
    $invalid[0]['evidence_sha256'] = 'invalid';
    DkP0ReleaseGate::evaluate('dolibarr-dk', '1.0.0', 'prod-1', $invalid, $at);
} catch (RuntimeException $e) {
    $invalidEvidenceBlocked = strpos($e->getMessage(), 'evidence SHA-256') !== false;
}
assert($invalidEvidenceBlocked);

$duplicateBlocked = false;
try {
    DkP0ReleaseGate::evaluate('dolibarr-dk', '1.0.0', 'prod-1', array($all[0], $all[0]), $at);
} catch (RuntimeException $e) {
    $duplicateBlocked = strpos($e->getMessage(), 'Duplicate') !== false;
}
assert($duplicateBlocked);

echo "P0 release gate tests passed\n";
