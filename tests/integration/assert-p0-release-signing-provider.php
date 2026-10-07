<?php

require_once __DIR__.'/../../dkmodul/class/Compliance/P0ReleaseSigningKeyProvider.php';
require_once __DIR__.'/../../dkmodul/class/Compliance/P0ReleaseReportSigner.php';
require_once __DIR__.'/../../dkmodul/class/Compliance/P0ReleaseGate.php';

$key = openssl_pkey_new(array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA));
if ($key === false || !openssl_pkey_export($key, $private)) {
    throw new RuntimeException('Unable to generate test RSA key');
}

$provider = new DkP0ReleaseSigningKeyProvider('p0-test-key', $private);
if ($provider->keyId() !== 'p0-test-key') throw new RuntimeException('Unexpected P0 signing key id');
if (!preg_match('/^[a-f0-9]{64}$/', $provider->publicKeySha256())) throw new RuntimeException('Invalid public key fingerprint');

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
        array('gate_id' => 'security-risk-evidence', 'status' => 'PASS', 'evidence_sha256' => $provider->publicKeySha256(), 'reason_codes' => array()),
    ),
    new DateTimeImmutable('2026-10-07T00:00:00+00:00')
);

$signature = DkP0ReleaseReportSigner::sign($report, $provider->privateKeyPem(), $provider->keyId());
if (!DkP0ReleaseReportSigner::verify($report, $signature, $provider->publicKeyPem())) {
    throw new RuntimeException('Provider-backed P0 report signature failed');
}

$otherKey = openssl_pkey_new(array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA));
$otherDetails = openssl_pkey_get_details($otherKey);
if (DkP0ReleaseReportSigner::verify($report, $signature, $otherDetails['key'])) {
    throw new RuntimeException('P0 report verified with wrong key');
}

echo "P0 release signing provider: PASS\n";
