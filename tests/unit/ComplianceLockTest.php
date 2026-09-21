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
        true
    );
} catch (RuntimeException $e) {
    $missingAttestationBlocked = strpos($e->getMessage(), 'deployment attestation') !== false;
}
assert($missingAttestationBlocked);

$invalidAttestationBlocked = false;
try {
    DkComplianceLock::assertDeploymentReady(
        __DIR__.'/../fixtures/product-manifest-registered-candidate.json',
        __DIR__.'/../fixtures/deployment-attestation-invalid-location.json',
        true
    );
} catch (RuntimeException $e) {
    $invalidAttestationBlocked = strpos($e->getMessage(), 'EU/EEA') !== false;
}
assert($invalidAttestationBlocked);

DkComplianceLock::assertDeploymentReady(
    __DIR__.'/../fixtures/product-manifest-registered-candidate.json',
    __DIR__.'/../fixtures/deployment-attestation-valid.json',
    true
);

echo "Compliance lock tests passed\n";
