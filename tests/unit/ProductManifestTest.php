<?php

require_once __DIR__.'/../../dkmodul/class/Compliance/ProductManifest.php';

$manifest = DkProductManifest::load(__DIR__.'/../../dkmodul/product-manifest.json');
assert($manifest->value('product.id') === 'dolibarr-dk');
assert($manifest->value('components.dolibarr') === '24.0.1');
assert($manifest->value('deployment.model') === 'hybrid-customer-hosted');
assert($manifest->value('deployment.hosting_policy') === 'customer-selectable-with-deployment-attestation');
assert($manifest->value('deployment.backup_policy.eu_eea_copy_required') === true);
assert($manifest->value('deployment.deployment_attestation.signature_required') === true);
assert($manifest->value('deployment.deployment_attestation.signature_algorithms') === array('RSA-SHA256'));
assert($manifest->value('deployment.deployment_attestation.trust_store_sha256') === 'TBD-production-trust-store-sha256');
assert($manifest->value('deployment.access_point.model') === 'own-certified-access-point');
assert($manifest->value('deployment.access_point.certification_status') === 'planned');

$blocked = false;
try {
    $manifest->assertRegistrable();
} catch (RuntimeException $e) {
    $blocked = strpos($e->getMessage(), 'not a registered-candidate') !== false;
}
assert($blocked);

$candidate = DkProductManifest::load(__DIR__.'/../fixtures/product-manifest-registered-candidate.json');
$candidate->assertRegistrable();

$candidateData = json_decode(file_get_contents(__DIR__.'/../fixtures/product-manifest-registered-candidate.json'), true);
$candidateData['deployment']['access_point']['certification_status'] = 'planned';
$plannedPath = tempnam(sys_get_temp_dir(), 'dk-manifest-');
file_put_contents($plannedPath, json_encode($candidateData));
$plannedBlocked = false;
try {
    DkProductManifest::load($plannedPath)->assertRegistrable();
} catch (RuntimeException $e) {
    $plannedBlocked = strpos($e->getMessage(), 'certified Nemhandel/Peppol access point') !== false;
} finally {
    unlink($plannedPath);
}
assert($plannedBlocked);

$candidateData['deployment']['access_point']['certification_status'] = 'certified';
$candidateData['deployment']['access_point']['valid_until'] = '2020-01-01';
$expiredPath = tempnam(sys_get_temp_dir(), 'dk-manifest-');
file_put_contents($expiredPath, json_encode($candidateData));
$expiredBlocked = false;
try {
    DkProductManifest::load($expiredPath)->assertRegistrable();
} catch (RuntimeException $e) {
    $expiredBlocked = strpos($e->getMessage(), 'invalid or expired') !== false;
} finally {
    unlink($expiredPath);
}
assert($expiredBlocked);

echo "Product manifest tests passed\n";
