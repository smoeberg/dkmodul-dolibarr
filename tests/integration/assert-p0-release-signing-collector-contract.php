<?php

require_once __DIR__.'/../../dkmodul/class/Compliance/P0ReleaseGateCollector.php';

final class P0ReleaseSigningCollectorTestDb
{
    public function prefix() { return 'llx_'; }
    public function escape($value) { return addslashes((string) $value); }
    public function lasterror() { return ''; }

    public function query($sql)
    {
        if (strpos($sql, 'SELECT check_uuid') === 0) {
            return new P0ReleaseSigningCollectorEmptyResult();
        }

        if (strpos($sql, 'INSERT INTO llx_dk_p0_release_report') === 0) {
            return true;
        }

        throw new RuntimeException('Unexpected SQL in release signing collector test: '.$sql);
    }

    public function fetch_object($result)
    {
        return null;
    }
}

final class P0ReleaseSigningCollectorEmptyResult
{
}

function writeReleaseSigningManifest(array $manifest)
{
    $path = tempnam(sys_get_temp_dir(), 'p0sign-manifest');
    file_put_contents($path, json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    return $path;
}

$manifest = json_decode(
    file_get_contents(__DIR__.'/../fixtures/product-manifest-registered-candidate.json'),
    true
);

$key = openssl_pkey_new(array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA));
if ($key === false || !openssl_pkey_export($key, $privateKeyPem)) {
    throw new RuntimeException('Unable to generate approved test release signing key');
}

$approvedProvider = new DkP0ReleaseSigningKeyProvider('p0-release-approved-test', $privateKeyPem);
$manifest['release_signing']['key_id'] = $approvedProvider->keyId();
$manifest['release_signing']['public_key_sha256'] = $approvedProvider->publicKeySha256();
$manifestPath = writeReleaseSigningManifest($manifest);

$collector = new DkP0ReleaseGateCollector(
    new DkP0ReleaseGateRepository(new P0ReleaseSigningCollectorTestDb()),
    $approvedProvider
);
$result = $collector->collectAndPersist(
    1,
    'deployment-test',
    $manifestPath,
    new DateTimeImmutable('2026-10-07T00:00:00+00:00')
);

$manifestGate = null;
foreach ($result['report']['gates'] as $gate) {
    if ($gate['gate_id'] === 'product-manifest') {
        $manifestGate = $gate;
        break;
    }
}
if ($manifestGate === null || $manifestGate['status'] !== 'PASS') {
    throw new RuntimeException('Approved release signing key did not produce a PASS product-manifest gate');
}
if (empty($result['report']['signature'])) {
    throw new RuntimeException('Approved release signing key did not produce a report signature');
}
if (!DkP0ReleaseReportSigner::verify(
    $result['report'],
    $result['report']['signature'],
    $approvedProvider->publicKeyPem()
)) {
    throw new RuntimeException('Approved P0 release report signature did not verify');
}

/*
 * A valid RSA signer is not sufficient if its identity/fingerprint is not
 * the one pinned by the registered manifest.
 */
$otherKey = openssl_pkey_new(array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA));
if ($otherKey === false || !openssl_pkey_export($otherKey, $otherPrivateKeyPem)) {
    throw new RuntimeException('Unable to generate unapproved test release signing key');
}
$unapprovedProvider = new DkP0ReleaseSigningKeyProvider('p0-release-unapproved-test', $otherPrivateKeyPem);
$unapprovedCollector = new DkP0ReleaseGateCollector(
    new DkP0ReleaseGateRepository(new P0ReleaseSigningCollectorTestDb()),
    $unapprovedProvider
);
$unapprovedResult = $unapprovedCollector->collectAndPersist(
    1,
    'deployment-test',
    $manifestPath,
    new DateTimeImmutable('2026-10-07T00:00:00+00:00')
);

$unapprovedManifestGate = null;
foreach ($unapprovedResult['report']['gates'] as $gate) {
    if ($gate['gate_id'] === 'product-manifest') {
        $unapprovedManifestGate = $gate;
        break;
    }
}
if ($unapprovedManifestGate === null
    || $unapprovedManifestGate['status'] !== 'BLOCKED'
    || $unapprovedManifestGate['reason_codes'] !== array('release-signing-key-not-approved')) {
    throw new RuntimeException('Unapproved release signing key was not blocked');
}
if (empty($unapprovedResult['report']['signature'])
    || !DkP0ReleaseReportSigner::verify(
        $unapprovedResult['report'],
        $unapprovedResult['report']['signature'],
        $unapprovedProvider->publicKeyPem()
    )) {
    throw new RuntimeException('Blocked report was not signed and self-verifiable');
}

@unlink($manifestPath);

echo "P0 release signing collector contract: PASS\\n";
