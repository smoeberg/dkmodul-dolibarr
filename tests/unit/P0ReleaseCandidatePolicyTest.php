<?php

require_once dirname(__DIR__, 2).'/dkmodul/class/Compliance/P0ReleaseGate.php';
require_once dirname(__DIR__, 2).'/dkmodul/class/Compliance/P0ReleaseReportSigner.php';
require_once dirname(__DIR__, 2).'/dkmodul/class/Compliance/P0ReleaseSigningKeyProvider.php';
require_once dirname(__DIR__, 2).'/dkmodul/class/Compliance/P0ReleaseSigningPolicy.php';
require_once dirname(__DIR__, 2).'/dkmodul/class/Compliance/P0ReleaseCandidatePolicy.php';

$key = openssl_pkey_new(array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA));
if ($key === false || !openssl_pkey_export($key, $privateKey)) {
    throw new RuntimeException('Unable to generate test key');
}
$details = openssl_pkey_get_details($key);
$provider = new DkP0ReleaseSigningKeyProvider('test-release-key', $privateKey);
$report = DkP0ReleaseGate::evaluate(
    'dolibarr-dk',
    '0.1.0-p0',
    'deployment-001',
    array(
        array('gate_id'=>'product-manifest','status'=>'PASS','evidence_sha256'=>str_repeat('a',64),'reason_codes'=>array()),
        array('gate_id'=>'deployment-attestation','status'=>'PASS','evidence_sha256'=>str_repeat('b',64),'reason_codes'=>array()),
        array('gate_id'=>'backup-retention-restore','status'=>'PASS','evidence_sha256'=>str_repeat('c',64),'reason_codes'=>array()),
        array('gate_id'=>'compliance-monitoring','status'=>'PASS','evidence_sha256'=>str_repeat('d',64),'reason_codes'=>array()),
        array('gate_id'=>'access-point-certification','status'=>'PASS','evidence_sha256'=>str_repeat('e',64),'reason_codes'=>array()),
        array('gate_id'=>'security-risk-evidence','status'=>'PASS','evidence_sha256'=>str_repeat('f',64),'reason_codes'=>array()),
    ),
    new DateTimeImmutable('2026-10-14T12:00:00Z')
);
$signature = DkP0ReleaseReportSigner::sign($report, $privateKey, 'test-release-key');
if (!DkP0ReleaseCandidatePolicy::assertEligible($report, $signature, $provider)) {
    throw new RuntimeException('Valid P0 release was not accepted');
}

$blocked = $report;
$blocked['decision'] = 'BLOCKED';
$failed = false;
try {
    DkP0ReleaseCandidatePolicy::assertEligible($blocked, $signature, $provider);
} catch (RuntimeException $e) {
    $failed = true;
}
if (!$failed) {
    throw new RuntimeException('Blocked P0 release was accepted as registered-candidate');
}

$tampered = $report;
$tampered['gates'][0]['status'] = 'BLOCKED';
$failed = false;
try {
    DkP0ReleaseCandidatePolicy::assertEligible($tampered, $signature, $provider);
} catch (RuntimeException $e) {
    $failed = true;
}
if (!$failed) {
    throw new RuntimeException('Tampered P0 release report was accepted');
}

echo "P0 release candidate policy tests passed\n";
