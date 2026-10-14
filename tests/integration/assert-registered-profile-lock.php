<?php

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);

require '/var/www/html/main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/dkmodul/core/triggers/interface_99_modDkmodul_DkmodulTriggers.class.php';

if (!getDolGlobalInt('DKMODUL_REGISTERED_PROFILE')) {
    throw new RuntimeException('Registered profile was not enabled for the negative integration test');
}

$trigger = new InterfaceDkmodulTriggers($db);
$object = new stdClass();
$object->id = 0;
$result = $trigger->runTrigger('BOOKKEEPING_CREATE', $object, $user, $langs, $conf);

if ($result >= 0) {
    throw new RuntimeException('Registered profile accepted an incomplete compliance deployment');
}

if (strpos($trigger->error, 'registered-candidate') === false
    && strpos($trigger->error, 'deployment attestation') === false) {
    throw new RuntimeException('Registered profile failed for an unexpected reason: '.$trigger->error);
}

$candidateManifest = '/var/www/dkmodul-fixtures/product-manifest-registered-candidate.json';
$missingAttestationBlocked = false;
try {
    DkComplianceLock::assertDeploymentReady(
        $candidateManifest,
        '',
        true,
        '/var/www/dkmodul-fixtures/attestation-trust-store.json'
    );
} catch (Throwable $e) {
    $missingAttestationBlocked = true;
}
if (!$missingAttestationBlocked) {
    throw new RuntimeException('Registered candidate accepted a missing deployment attestation');
}

$invalidAttestationBlocked = false;
try {
    DkComplianceLock::assertDeploymentReady(
        $candidateManifest,
        '/var/www/dkmodul-fixtures/deployment-attestation-invalid-location.json',
        true,
        '/var/www/dkmodul-fixtures/attestation-trust-store.json'
    );
} catch (Throwable $e) {
    $invalidAttestationBlocked = true;
}
if (!$invalidAttestationBlocked) {
    throw new RuntimeException('Registered candidate accepted an invalid deployment attestation');
}

$attestationPayload = json_decode(
    file_get_contents('/var/www/dkmodul-fixtures/deployment-attestation-valid.json'),
    true,
    512,
    JSON_THROW_ON_ERROR
);
$keyPair = openssl_pkey_new(array(
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
    'private_key_bits' => 2048,
));
if ($keyPair === false) {
    throw new RuntimeException('Unable to create integration attestation signing key');
}
openssl_pkey_export($keyPair, $privateKeyPem);
$keyDetails = openssl_pkey_get_details($keyPair);
if (!is_array($keyDetails) || !is_string($keyDetails['key'] ?? null)) {
    throw new RuntimeException('Unable to extract integration attestation public key');
}
$publicKeyPem = $keyDetails['key'];
$trustStore = array(
    'schema_version' => 1,
    'keys' => array(array(
        'key_id' => 'integration-p0-test',
        'algorithm' => 'RSA-SHA256',
        'status' => 'active',
        'public_key_pem' => $publicKeyPem,
    )),
);
$runtimeDir = sys_get_temp_dir().'/dkmodul-registered-profile-'.bin2hex(random_bytes(6));
if (!mkdir($runtimeDir, 0700, true)) {
    throw new RuntimeException('Unable to create integration attestation runtime directory');
}
$trustStorePath = $runtimeDir.'/attestation-trust-store.json';
$attestationPath = $runtimeDir.'/deployment-attestation-signed.json';
$manifestPath = $runtimeDir.'/product-manifest-registered-candidate.json';
file_put_contents($trustStorePath, json_encode($trustStore, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
$payloadJson = json_encode($attestationPayload, JSON_THROW_ON_ERROR);
openssl_sign($payloadJson, $signature, $privateKeyPem, OPENSSL_ALGO_SHA256);
file_put_contents($attestationPath, json_encode(array(
    'schema_version' => 1,
    'signed_payload' => base64_encode($payloadJson),
    'signature' => array(
        'algorithm' => 'RSA-SHA256',
        'key_id' => 'integration-p0-test',
        'value' => base64_encode($signature),
    ),
), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
$manifest = json_decode(file_get_contents($candidateManifest), true, 512, JSON_THROW_ON_ERROR);
$trustStoreSha256 = hash_file('sha256', $trustStorePath);
$manifest['deployment']['deployment_attestation']['trust_store_sha256'] = $trustStoreSha256;
$manifest['deployment']['deployment_attestation']['security_risk_evidence']['trust_store_sha256'] = $trustStoreSha256;
file_put_contents($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

DkComplianceLock::assertDeploymentReady(
    $manifestPath,
    $attestationPath,
    true,
    $trustStorePath
);

echo "Registered profile fail-closed integration test passed\n";
