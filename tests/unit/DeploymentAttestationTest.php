<?php

require_once __DIR__.'/../../dkmodul/class/Compliance/DeploymentAttestation.php';

$attestation = DkDeploymentAttestation::load(__DIR__.'/../fixtures/deployment-attestation-valid.json');
assert($attestation['deployment_id'] === 'example-production');

$signedAttestation = DkDeploymentAttestation::loadSigned(
    __DIR__.'/../fixtures/deployment-attestation-signed-valid.json',
    __DIR__.'/../fixtures/attestation-trust-store.json',
    'c80be142448ef0110f72816f8935845e58562390d0606f3e1a5d3c041d095367'
);
assert($signedAttestation['deployment_id'] === 'example-production');

$tamperedEnvelope = json_decode(file_get_contents(__DIR__.'/../fixtures/deployment-attestation-signed-valid.json'), true);
$tamperedEnvelope['signed_payload'][10] = $tamperedEnvelope['signed_payload'][10] === 'A' ? 'B' : 'A';
$tamperedPath = tempnam(sys_get_temp_dir(), 'dk-attestation-');
file_put_contents($tamperedPath, json_encode($tamperedEnvelope));
$tamperingBlocked = false;
try {
    DkDeploymentAttestation::loadSigned(
        $tamperedPath,
        __DIR__.'/../fixtures/attestation-trust-store.json',
        'c80be142448ef0110f72816f8935845e58562390d0606f3e1a5d3c041d095367'
    );
} catch (RuntimeException $e) {
    $tamperingBlocked = strpos($e->getMessage(), 'signature verification failed') !== false;
} finally {
    unlink($tamperedPath);
}
assert($tamperingBlocked);

$revokedTrustStore = json_decode(file_get_contents(__DIR__.'/../fixtures/attestation-trust-store.json'), true);
$revokedTrustStore['keys'][0]['status'] = 'revoked';
$revokedTrustStorePath = tempnam(sys_get_temp_dir(), 'dk-trust-store-');
file_put_contents($revokedTrustStorePath, json_encode($revokedTrustStore));
$revokedKeyBlocked = false;
try {
    DkDeploymentAttestation::loadSigned(
        __DIR__.'/../fixtures/deployment-attestation-signed-valid.json',
        $revokedTrustStorePath,
        hash_file('sha256', $revokedTrustStorePath)
    );
} catch (RuntimeException $e) {
    $revokedKeyBlocked = strpos($e->getMessage(), 'not trusted or active') !== false;
} finally {
    unlink($revokedTrustStorePath);
}
assert($revokedKeyBlocked);

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
