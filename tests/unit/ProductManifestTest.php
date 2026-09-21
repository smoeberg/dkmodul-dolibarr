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

$blocked = false;
try {
    $manifest->assertRegistrable();
} catch (RuntimeException $e) {
    $blocked = strpos($e->getMessage(), 'not a registered-candidate') !== false;
}
assert($blocked);

echo "Product manifest tests passed\n";
