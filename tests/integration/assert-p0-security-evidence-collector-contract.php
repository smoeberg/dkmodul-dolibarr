<?php

require_once __DIR__.'/../../dkmodul/class/Compliance/P0ReleaseGateCollector.php';

final class P0CollectorTestDb
{
    public function prefix() { return 'llx_'; }
    public function escape($value) { return addslashes((string) $value); }
    public function lasterror() { return ''; }

    public function query($sql)
    {
        if (strpos($sql, 'SELECT check_uuid') === 0) {
            return new P0CollectorEmptyResult();
        }

        if (strpos($sql, 'INSERT INTO llx_dk_p0_release_report') === 0) {
            return true;
        }

        throw new RuntimeException('Unexpected SQL in collector test: '.$sql);
    }

    public function fetch_object($result)
    {
        return null;
    }
}

final class P0CollectorEmptyResult
{
}

function writeJsonFile($prefix, array $data)
{
    $path = tempnam(sys_get_temp_dir(), $prefix);
    file_put_contents($path, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    return $path;
}

$key = openssl_pkey_new(array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA));
if ($key === false || !openssl_pkey_export($key, $private)) {
    throw new RuntimeException('Unable to generate test RSA key');
}
$details = openssl_pkey_get_details($key);

$trustStore = array(
    'schema_version' => 1,
    'keys' => array(
        array(
            'key_id' => 'risk-test-key',
            'algorithm' => 'RSA-SHA256',
            'status' => 'active',
            'public_key_pem' => $details['key'],
        ),
    ),
);
$trustStorePath = writeJsonFile('p0collector-trust', $trustStore);
$trustStoreSha256 = hash_file('sha256', $trustStorePath);

$manifest = json_decode(
    file_get_contents(__DIR__.'/../fixtures/product-manifest-registered-candidate.json'),
    true
);
$manifest['deployment']['deployment_attestation']['trust_store_sha256'] = $trustStoreSha256;
$manifest['deployment']['deployment_attestation']['security_risk_evidence']['trust_store_sha256'] = $trustStoreSha256;
$manifestPath = writeJsonFile('p0collector-manifest', $manifest);

$payload = array(
    'schema_version' => 1,
    'deployment_id' => 'deployment-test',
    'status' => 'approved',
    'reviewer' => 'security-reviewer',
    'assessed_at' => '2026-10-01T00:00:00+00:00',
    'valid_until' => '2026-11-01T00:00:00+00:00',
    'risk_register_sha256' => str_repeat('a', 64),
    'controls_sha256' => str_repeat('b', 64),
);
$payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
openssl_sign($payloadJson, $signature, $private, OPENSSL_ALGO_SHA256);
$evidencePath = writeJsonFile('p0collector-evidence', array(
    'schema_version' => 1,
    'signed_payload' => base64_encode($payloadJson),
    'signature' => array(
        'algorithm' => 'RSA-SHA256',
        'key_id' => 'risk-test-key',
        'value' => base64_encode($signature),
    ),
));

putenv('DKMODUL_ATTESTATION_TRUST_STORE_PATH='.$trustStorePath);
putenv('DKMODUL_P0_SECURITY_RISK_EVIDENCE_PATH='.$evidencePath);

$db = new P0CollectorTestDb();
$repository = new DkP0ReleaseGateRepository($db);
$collector = new DkP0ReleaseGateCollector($repository);
$now = new DateTimeImmutable('2026-10-07T00:00:00+00:00');

$result = $collector->collectAndPersist(1, 'deployment-test', $manifestPath, $now);
$securityGate = null;
foreach ($result['report']['gates'] as $gate) {
    if ($gate['gate_id'] === 'security-risk-evidence') {
        $securityGate = $gate;
        break;
    }
}
if ($securityGate === null || $securityGate['status'] !== 'PASS') {
    throw new RuntimeException('Valid manifest security-risk evidence did not produce PASS');
}

/*
 * The collector must treat the manifest contract as authoritative. Changing
 * the contract to required=false must block the gate rather than falling back
 * to the configured evidence path and trusting otherwise valid evidence.
 */
$invalidManifest = $manifest;
$invalidManifest['deployment']['deployment_attestation']['security_risk_evidence']['required'] = false;
$invalidManifestPath = writeJsonFile('p0collector-invalid-manifest', $invalidManifest);

$invalidResult = $collector->collectAndPersist(1, 'deployment-test', $invalidManifestPath, $now);
$invalidSecurityGate = null;
foreach ($invalidResult['report']['gates'] as $gate) {
    if ($gate['gate_id'] === 'security-risk-evidence') {
        $invalidSecurityGate = $gate;
        break;
    }
}
if ($invalidSecurityGate === null
    || $invalidSecurityGate['status'] !== 'BLOCKED'
    || $invalidSecurityGate['reason_codes'] !== array('security-risk-evidence-contract-invalid')) {
    throw new RuntimeException('Invalid manifest security-risk evidence contract was not blocked');
}

@unlink($trustStorePath);
@unlink($manifestPath);
@unlink($invalidManifestPath);
@unlink($evidencePath);
putenv('DKMODUL_ATTESTATION_TRUST_STORE_PATH');
putenv('DKMODUL_P0_SECURITY_RISK_EVIDENCE_PATH');

echo "P0 security evidence collector contract: PASS\\n";
