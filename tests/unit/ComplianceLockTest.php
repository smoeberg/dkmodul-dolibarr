<?php

require_once __DIR__.'/../../dkmodul/class/Compliance/ComplianceLock.php';

assert(DkComplianceLock::triggerDecision(false, false, false) === 'module-disabled');
assert(DkComplianceLock::triggerDecision(true, false, false) === 'development-bypass');
assert(DkComplianceLock::triggerDecision(true, false, true) === 'enforce');
assert(DkComplianceLock::triggerDecision(true, true, true) === 'enforce');

$disabledBlocked = false;
try {
    DkComplianceLock::triggerDecision(true, true, false);
} catch (RuntimeException $e) {
    $disabledBlocked = true;
}
assert($disabledBlocked);

$removalBlocked = false;
try {
    DkComplianceLock::assertRemovalAllowed(true);
} catch (RuntimeException $e) {
    $removalBlocked = true;
}
assert($removalBlocked);

DkComplianceLock::assertRemovalAllowed(false);
DkComplianceLock::assertDeploymentReady(__DIR__.'/../../dkmodul/product-manifest.json', '', false);

$missingAttestationBlocked = false;
try {
    DkComplianceLock::assertDeploymentReady(
        __DIR__.'/../fixtures/product-manifest-registered-candidate.json',
        '',
        true,
        __DIR__.'/../fixtures/attestation-trust-store.json'
    );
} catch (RuntimeException $e) {
    $missingAttestationBlocked = strpos($e->getMessage(), 'deployment attestation') !== false;
}
assert($missingAttestationBlocked);

$dir = sys_get_temp_dir().'/dk-compliance-lock-'.bin2hex(random_bytes(4));
mkdir($dir, 0700, true);

$key = openssl_pkey_new(array(
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
    'private_key_bits' => 2048,
));
assert($key !== false);
openssl_pkey_export($key, $privatePem);
$details = openssl_pkey_get_details($key);
$publicPem = $details['key'];
$keyId = 'compliance-lock-test-key';

$trustStore = array(
    'schema_version' => 1,
    'keys' => array(array(
        'key_id' => $keyId,
        'algorithm' => 'RSA-SHA256',
        'status' => 'active',
        'public_key_pem' => $publicPem,
    )),
);
$trustStorePath = $dir.'/trust.json';
file_put_contents($trustStorePath, json_encode($trustStore, JSON_UNESCAPED_SLASHES));

$payload = json_decode(file_get_contents(__DIR__.'/../fixtures/deployment-attestation-valid.json'), true);
$payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
openssl_sign($payloadJson, $signature, $privatePem, OPENSSL_ALGO_SHA256);
$attestationPath = $dir.'/attestation.json';
file_put_contents($attestationPath, json_encode(array(
    'schema_version' => 1,
    'signed_payload' => base64_encode($payloadJson),
    'signature' => array(
        'key_id' => $keyId,
        'algorithm' => 'RSA-SHA256',
        'value' => base64_encode($signature),
    ),
), JSON_UNESCAPED_SLASHES));

$manifest = json_decode(file_get_contents(__DIR__.'/../../dkmodul/product-manifest.json'), true);
$manifest['deployment']['deployment_attestation']['trust_store_sha256'] = hash_file('sha256', $trustStorePath);
$manifestPath = $dir.'/manifest.json';
file_put_contents($manifestPath, json_encode($manifest, JSON_UNESCAPED_SLASHES));

$invalidPayload = json_decode(file_get_contents(__DIR__.'/../fixtures/deployment-attestation-invalid-location.json'), true);
$invalidPayloadJson = json_encode($invalidPayload, JSON_UNESCAPED_SLASHES);
openssl_sign($invalidPayloadJson, $invalidSignature, $privatePem, OPENSSL_ALGO_SHA256);
$invalidAttestationPath = $dir.'/invalid-attestation.json';
file_put_contents($invalidAttestationPath, json_encode(array(
    'schema_version' => 1,
    'signed_payload' => base64_encode($invalidPayloadJson),
    'signature' => array(
        'key_id' => $keyId,
        'algorithm' => 'RSA-SHA256',
        'value' => base64_encode($invalidSignature),
    ),
), JSON_UNESCAPED_SLASHES));

DkComplianceLock::assertDeploymentReady($manifestPath, $attestationPath, true, $trustStorePath);

$invalidAttestationBlocked = false;
try {
    DkComplianceLock::assertDeploymentReady($manifestPath, $invalidAttestationPath, true, $trustStorePath);
} catch (RuntimeException $e) {
    $invalidAttestationBlocked = strpos($e->getMessage(), 'EU/EEA') !== false;
}
assert($invalidAttestationBlocked);

unlink($manifestPath);
unlink($attestationPath);
unlink($invalidAttestationPath);
unlink($trustStorePath);
rmdir($dir);

echo "Compliance lock tests passed\n";
