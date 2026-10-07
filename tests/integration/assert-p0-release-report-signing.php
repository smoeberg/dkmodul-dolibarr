<?php

require_once __DIR__.'/../../dkmodul/class/Compliance/P0ReleaseGate.php';
require_once __DIR__.'/../../dkmodul/class/Compliance/P0ReleaseReportSigner.php';

$private = trim(shell_exec('openssl genrsa 2048 2>/dev/null'));
if ($private === '') {
    throw new RuntimeException('openssl genrsa failed');
}
$public = trim(shell_exec('printf %s '.escapeshellarg($private).' | openssl rsa -pubout 2>/dev/null'));
if ($public === '') {
    throw new RuntimeException('openssl rsa -pubout failed');
}

$report = DkP0ReleaseGate::evaluate(
    'dkmodul-dolibarr',
    'test',
    'deployment-test',
    array(
        array('gate_id' => 'product-manifest', 'status' => 'PASS', 'evidence_sha256' => str_repeat('a', 64), 'reason_codes' => array()),
        array('gate_id' => 'deployment-attestation', 'status' => 'PASS', 'evidence_sha256' => str_repeat('b', 64), 'reason_codes' => array()),
        array('gate_id' => 'backup-retention-restore', 'status' => 'PASS', 'evidence_sha256' => str_repeat('c', 64), 'reason_codes' => array()),
        array('gate_id' => 'compliance-monitoring', 'status' => 'PASS', 'evidence_sha256' => str_repeat('d', 64), 'reason_codes' => array()),
        array('gate_id' => 'access-point-certification', 'status' => 'PASS', 'evidence_sha256' => str_repeat('e', 64), 'reason_codes' => array()),
        array('gate_id' => 'security-risk-evidence', 'status' => 'PASS', 'evidence_sha256' => str_repeat('f', 64), 'reason_codes' => array()),
    ),
    new DateTimeImmutable('2026-10-07T00:00:00+00:00')
);

$signed = DkP0ReleaseReportSigner::sign($report, $private, 'test-key');
if (!DkP0ReleaseReportSigner::verify($report, $signed, $public)) {
    throw new RuntimeException('P0 report signature verification failed');
}

$report['decision'] = 'FAIL';
if (DkP0ReleaseReportSigner::verify($report, $signed, $public)) {
    throw new RuntimeException('Tampered P0 report unexpectedly verified');
}

echo "P0 release report signing: PASS\n";
