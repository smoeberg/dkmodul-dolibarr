<?php

require_once __DIR__.'/../../dkmodul/class/Compliance/P0ReleaseGate.php';
require_once __DIR__.'/../../dkmodul/class/Compliance/P0ReleaseReportSigner.php';
require_once __DIR__.'/../../dkmodul/class/Compliance/P0ReleaseSigningKeyProvider.php';
require_once __DIR__.'/../../dkmodul/class/Compliance/P0ReleaseSigningPolicy.php';

$key = openssl_pkey_new(array(
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
    'private_key_bits' => 2048,
));
assert($key !== false);
openssl_pkey_export($key, $privateKeyPem);
$details = openssl_pkey_get_details($key);
assert(is_array($details));
assert(isset($details['key']));

$provider = new DkP0ReleaseSigningKeyProvider('approved-release-key-1', $privateKeyPem);
assert(DkP0ReleaseSigningPolicy::assertApproved(
    $provider,
    'approved-release-key-1',
    $provider->publicKeySha256()
) === true);

$failed = false;
try {
    DkP0ReleaseSigningPolicy::assertApproved($provider, 'other-key', $provider->publicKeySha256());
} catch (RuntimeException $e) {
    $failed = true;
}
assert($failed === true);

$failed = false;
try {
    DkP0ReleaseSigningPolicy::assertApproved($provider, 'approved-release-key-1', str_repeat('0', 64));
} catch (RuntimeException $e) {
    $failed = true;
}
assert($failed === true);

$report = array(
    'schema_version' => 1,
    'status' => 'p0-draft',
    'report_sha256' => hash('sha256', '{"status":"p0-draft"}'),
);
$signature = DkP0ReleaseReportSigner::sign($report, $privateKeyPem, $provider->keyId());
assert(DkP0ReleaseSigningPolicy::assertSignatureMatchesProvider($report, $signature, $provider) === true);

$tamperedSignature = $signature;
$tamperedSignature['key_id'] = 'other-key';
$failed = false;
try {
    DkP0ReleaseSigningPolicy::assertSignatureMatchesProvider($report, $tamperedSignature, $provider);
} catch (RuntimeException $e) {
    $failed = true;
}
assert($failed === true);

echo "P0 release signing approval policy tests passed\n";
