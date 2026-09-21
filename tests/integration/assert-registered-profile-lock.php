<?php

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

$candidateManifest = '/var/www/dkmodul-tests/fixtures/product-manifest-registered-candidate.json';
$missingAttestationBlocked = false;
try {
    DkComplianceLock::assertDeploymentReady(
        $candidateManifest,
        '',
        true,
        '/var/www/dkmodul-tests/fixtures/attestation-trust-store.json'
    );
} catch (RuntimeException $e) {
    $missingAttestationBlocked = strpos($e->getMessage(), 'deployment attestation') !== false;
}
if (!$missingAttestationBlocked) {
    throw new RuntimeException('Registered candidate accepted a missing deployment attestation');
}

$invalidAttestationBlocked = false;
try {
    DkComplianceLock::assertDeploymentReady(
        $candidateManifest,
        '/var/www/dkmodul-tests/fixtures/deployment-attestation-invalid-location.json',
        true,
        '/var/www/dkmodul-tests/fixtures/attestation-trust-store.json'
    );
} catch (RuntimeException $e) {
    $invalidAttestationBlocked = strpos($e->getMessage(), 'signed deployment attestation envelope') !== false;
}
if (!$invalidAttestationBlocked) {
    throw new RuntimeException('Registered candidate accepted an invalid deployment attestation');
}

DkComplianceLock::assertDeploymentReady(
    $candidateManifest,
    '/var/www/dkmodul-tests/fixtures/deployment-attestation-signed-valid.json',
    true,
    '/var/www/dkmodul-tests/fixtures/attestation-trust-store.json'
);

echo "Registered profile fail-closed integration test passed\n";
