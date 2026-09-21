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
DkComplianceLock::assertManifestReady(__DIR__.'/../../dkmodul/product-manifest.json', false);

echo "Compliance lock tests passed\n";
