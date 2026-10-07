<?php

require_once __DIR__.'/../../dkmodul/class/Compliance/BackupConfiguration.php';

$manifest = json_decode(file_get_contents(__DIR__.'/../../dkmodul/product-manifest.json'), true);
$manifest['deployment']['deployment_attestation']['trust_store_sha256']
    = 'c80be142448ef0110f72816f8935845e58562390d0606f3e1a5d3c041d095367';
$manifestPath = tempnam(sys_get_temp_dir(), 'dk-manifest-');
file_put_contents($manifestPath, json_encode($manifest));

$settings = array(
    'DKMODUL_COMPLIANCE_MODE' => '1',
    'DKMODUL_DEPLOYMENT_ATTESTATION_PATH' => __DIR__.'/../fixtures/deployment-attestation-signed-valid.json',
    'DKMODUL_ATTESTATION_TRUST_STORE_PATH' => __DIR__.'/../fixtures/attestation-trust-store.json',
    'DKMODUL_DEPLOYMENT_ID' => 'example-production',
    'DKMODUL_REQUIRED_RETAIN_UNTIL' => '2031-12-31',
    'DKMODUL_HOSTING_REGISTRATION' => 'DK12345678',
);

$configuration = DkBackupConfiguration::resolve($settings, $manifestPath);
assert($configuration['deployment_id'] === 'example-production');
assert($configuration['backup']['legal_name'] === 'Independent Backup ApS');
assert($configuration['backup']['registration_number'] === 'DK87654321');
assert($configuration['backup']['country'] === 'DK');
assert($configuration['backup']['full_schedule'] === 'weekly');
assert($configuration['backup']['incremental_schedule'] === 'daily');

$summary = DkBackupConfiguration::summarize($configuration);
assert($summary['provider'] === 'Independent Backup ApS');
assert($summary['provider_country'] === 'DK');
assert($summary['full_schedule'] === 'weekly');

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

echo "BackupConfiguration unit test passed\n";
