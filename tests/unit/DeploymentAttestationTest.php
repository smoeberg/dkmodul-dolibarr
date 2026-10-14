<?php

require_once __DIR__.'/../../dkmodul/class/Compliance/DeploymentAttestation.php';

$attestation = DkDeploymentAttestation::load(__DIR__.'/../fixtures/deployment-attestation-valid.json');
assert($attestation['deployment_id'] === 'example-production');
assert($attestation['backup']['full_schedule'] === 'weekly:sunday:02:00');
assert($attestation['backup']['incremental_schedule'] === 'daily:02:00,14:00');
assert($attestation['backup']['retention_policy'] === 'five-years-from-financial-year-end');
assert($attestation['backup']['early_delete_denied'] === true);

$dir = sys_get_temp_dir().'/dk-attestation-'.bin2hex(random_bytes(4));
mkdir($dir, 0700, true);
$key = openssl_pkey_new(array(
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
    'private_key_bits' => 2048,
));
assert($key !== false);
openssl_pkey_export($key, $privatePem);
$details = openssl_pkey_get_details($key);
$publicPem = $details['key'];
$keyId = 'p0-test-runtime';

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

$payloadJson = json_encode($attestation, JSON_UNESCAPED_SLASHES);
openssl_sign($payloadJson, $signature, $privatePem, OPENSSL_ALGO_SHA256);
$signedPath = $dir.'/signed.json';
file_put_contents($signedPath, json_encode(array(
    'schema_version' => 1,
    'signed_payload' => base64_encode($payloadJson),
    'signature' => array(
        'key_id' => $keyId,
        'algorithm' => 'RSA-SHA256',
        'value' => base64_encode($signature),
    ),
), JSON_UNESCAPED_SLASHES));

$signedAttestation = DkDeploymentAttestation::loadSigned(
    $signedPath,
    $trustStorePath,
    hash_file('sha256', $trustStorePath)
);
assert($signedAttestation['deployment_id'] === 'example-production');

$tamperedEnvelope = json_decode(file_get_contents($signedPath), true);
$tamperedEnvelope['signed_payload'][10] = $tamperedEnvelope['signed_payload'][10] === 'A' ? 'B' : 'A';
$tamperedPath = $dir.'/tampered.json';
file_put_contents($tamperedPath, json_encode($tamperedEnvelope));
$tamperingBlocked = false;
try {
    DkDeploymentAttestation::loadSigned($tamperedPath, $trustStorePath, hash_file('sha256', $trustStorePath));
} catch (RuntimeException $e) {
    $tamperingBlocked = strpos($e->getMessage(), 'signature verification failed') !== false;
}
assert($tamperingBlocked);

$revokedTrustStore = $trustStore;
$revokedTrustStore['keys'][0]['status'] = 'revoked';
$revokedTrustStorePath = $dir.'/revoked-trust.json';
file_put_contents($revokedTrustStorePath, json_encode($revokedTrustStore));
$revokedKeyBlocked = false;
try {
    DkDeploymentAttestation::loadSigned($signedPath, $revokedTrustStorePath, hash_file('sha256', $revokedTrustStorePath));
} catch (RuntimeException $e) {
    $revokedKeyBlocked = strpos($e->getMessage(), 'not trusted or active') !== false;
}
assert($revokedKeyBlocked);

$invalidSchedule = $attestation;
$invalidSchedule['backup']['full_schedule'] = 'weekly';
$invalidPath = $dir.'/invalid.json';
file_put_contents($invalidPath, json_encode($invalidSchedule));
$scheduleBlocked = false;
try {
    DkDeploymentAttestation::load($invalidPath);
} catch (RuntimeException $e) {
    $scheduleBlocked = strpos($e->getMessage(), 'canonical weekly syntax') !== false;
}
assert($scheduleBlocked);

$invalidRetention = $attestation;
$invalidRetention['backup']['retention_policy'] = 'five-years';
file_put_contents($invalidPath, json_encode($invalidRetention));
$retentionBlocked = false;
try {
    DkDeploymentAttestation::load($invalidPath);
} catch (RuntimeException $e) {
    $retentionBlocked = strpos($e->getMessage(), 'retention policy') !== false;
}
assert($retentionBlocked);

$invalidEarlyDelete = $attestation;
$invalidEarlyDelete['backup']['early_delete_denied'] = false;
file_put_contents($invalidPath, json_encode($invalidEarlyDelete));
$earlyDeleteBlocked = false;
try {
    DkDeploymentAttestation::load($invalidPath);
} catch (RuntimeException $e) {
    $earlyDeleteBlocked = strpos($e->getMessage(), 'early deletion') !== false;
}
assert($earlyDeleteBlocked);

$missingBlocked = false;
try {
    DkDeploymentAttestation::load($dir.'/does-not-exist.json');
} catch (RuntimeException $e) {
    $missingBlocked = true;
}
assert($missingBlocked);

$locationInvalid = $attestation;
$locationInvalid['backup']['country'] = 'US';
file_put_contents($invalidPath, json_encode($locationInvalid));
$locationBlocked = false;
try {
    DkDeploymentAttestation::load($invalidPath);
} catch (RuntimeException $e) {
    $locationBlocked = strpos($e->getMessage(), 'EU/EEA') !== false;
}
assert($locationBlocked);

unlink($invalidPath);
unlink($tamperedPath);
unlink($signedPath);
unlink($revokedTrustStorePath);
unlink($trustStorePath);
rmdir($dir);

echo "Deployment attestation tests passed\n";
