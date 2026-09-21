<?php

require_once __DIR__.'/../../dkmodul/class/Compliance/DeploymentAttestation.php';

$attestation = DkDeploymentAttestation::load(__DIR__.'/../fixtures/deployment-attestation-valid.json');
assert($attestation['deployment_id'] === 'example-production');

$missingBlocked = false;
try {
    DkDeploymentAttestation::load(__DIR__.'/../fixtures/does-not-exist.json');
} catch (RuntimeException $e) {
    $missingBlocked = true;
}
assert($missingBlocked);

$locationBlocked = false;
try {
    DkDeploymentAttestation::load(__DIR__.'/../fixtures/deployment-attestation-invalid-location.json');
} catch (RuntimeException $e) {
    $locationBlocked = strpos($e->getMessage(), 'EU/EEA') !== false;
}
assert($locationBlocked);

echo "Deployment attestation tests passed\n";
