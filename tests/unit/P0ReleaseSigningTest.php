<?php

require_once __DIR__.'/../../dkmodul/class/Compliance/P0ReleaseGate.php';
require_once __DIR__.'/../../dkmodul/class/Compliance/P0ReleaseReportSigner.php';
require_once __DIR__.'/../../dkmodul/class/Compliance/P0ReleaseSigningKeyProvider.php';

$key = openssl_pkey_new(array(
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
    'private_key_bits' => 2048,
));
assert($key !== false);
openssl_pkey_export($key, $privateKeyPem);
$details = openssl_pkey_get_details($key);
assert(is_array($details));
assert(isset($details['key']));

$report = array(
    'schema_version' => 1,
    'status' => 'p0-draft',
    'report_sha256' => hash('sha256', '{"status":"p0-draft"}'),
);

$signature = DkP0ReleaseReportSigner::sign($report, $privateKeyPem, 'test-release-key-1');
assert($signature['signature_type'] === 'openssl-rsa-sha256');
assert($signature['key_id'] === 'test-release-key-1');
assert(DkP0ReleaseReportSigner::verify($report, $signature, $details['key']) === true);

$tampered = $report;
$tampered['status'] = 'registered-candidate';
assert(DkP0ReleaseReportSigner::verify($tampered, $signature, $details['key']) === false);

$provider = new DkP0ReleaseSigningKeyProvider('test-release-key-1', $privateKeyPem);
assert($provider->keyId() === 'test-release-key-1');
assert($provider->publicKeySha256() === hash('sha256', $provider->publicKeyPem()));

$failed = false;
try {
    new DkP0ReleaseSigningKeyProvider('test-release-key-1', 'not-a-key');
} catch (RuntimeException $e) {
    $failed = true;
}
assert($failed === true);

echo "P0 release signing tests passed\n";
