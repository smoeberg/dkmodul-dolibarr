<?php

require_once __DIR__.'/../../dkmodul/class/Compliance/BackupConfiguration.php';

$dir = sys_get_temp_dir().'/dk-backup-config-'.bin2hex(random_bytes(4));
mkdir($dir, 0700, true);

$key = openssl_pkey_new(array(
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
    'private_key_bits' => 2048,
));
assert($key !== false);
openssl_pkey_export($key, $privatePem);
$details = openssl_pkey_get_details($key);
$publicPem = $details['key'];

$trustStore = array(
    'schema_version' => 1,
    'keys' => array(array(
        'key_id' => 'backup-config-test-key',
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
        'key_id' => 'backup-config-test-key',
        'algorithm' => 'RSA-SHA256',
        'value' => base64_encode($signature),
    ),
), JSON_UNESCAPED_SLASHES));

$manifest = json_decode(file_get_contents(__DIR__.'/../../dkmodul/product-manifest.json'), true);
$manifest['deployment']['deployment_attestation']['trust_store_sha256'] = hash_file('sha256', $trustStorePath);
$manifestPath = $dir.'/manifest.json';
file_put_contents($manifestPath, json_encode($manifest));

$settings = array(
    'DKMODUL_COMPLIANCE_MODE' => '1',
    'DKMODUL_DEPLOYMENT_ATTESTATION_PATH' => $attestationPath,
    'DKMODUL_ATTESTATION_TRUST_STORE_PATH' => $trustStorePath,
    'DKMODUL_DEPLOYMENT_ID' => 'example-production',
    'DKMODUL_REQUIRED_RETAIN_UNTIL' => '2031-12-31',
    'DKMODUL_HOSTING_REGISTRATION' => 'DK12345678',
);

$configuration = DkBackupConfiguration::resolve($settings, $manifestPath);
assert($configuration['deployment_id'] === 'example-production');
assert($configuration['backup']['legal_name'] === 'Independent Backup ApS');
assert($configuration['backup']['registration_number'] === 'DK87654321');
assert($configuration['backup']['country'] === 'DK');
assert($configuration['backup']['full_schedule'] === 'weekly:sunday:02:00');
assert($configuration['backup']['incremental_schedule'] === 'daily:02:00,14:00');
assert($configuration['backup']['retention_policy'] === 'five-years-from-financial-year-end');
assert($configuration['backup']['early_delete_denied'] === true);

$summary = DkBackupConfiguration::summarize($configuration);
assert($summary['provider'] === 'Independent Backup ApS');
assert($summary['provider_country'] === 'DK');
assert($summary['full_schedule'] === 'weekly:sunday:02:00');

$disabledBlocked = false;
try {
    DkBackupConfiguration::resolve(array(), $manifestPath);
} catch (RuntimeException $e) {
    $disabledBlocked = strpos($e->getMessage(), 'compliance mode is disabled') !== false;
}
assert($disabledBlocked);

$mismatchedSettings = $settings;
$mismatchedSettings['DKMODUL_DEPLOYMENT_ID'] = 'other-deployment';
$mismatchBlocked = false;
try {
    DkBackupConfiguration::resolve($mismatchedSettings, $manifestPath);
} catch (RuntimeException $e) {
    $mismatchBlocked = strpos($e->getMessage(), 'Deployment identifier does not match') !== false;
}
assert($mismatchBlocked);

unlink($manifestPath);
unlink($attestationPath);
unlink($trustStorePath);
rmdir($dir);

echo "BackupConfiguration unit test passed\n";
