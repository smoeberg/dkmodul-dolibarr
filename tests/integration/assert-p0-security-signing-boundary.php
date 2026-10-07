<?php

require_once __DIR__.'/../../dkmodul/class/Compliance/P0SecurityRiskEvidence.php';

function makeRsaKeypair()
{
    $key = openssl_pkey_new(array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA));
    if ($key === false || !openssl_pkey_export($key, $private)) {
        throw new RuntimeException('Unable to generate test RSA key');
    }
    $details = openssl_pkey_get_details($key);
    return array('private' => $private, 'public' => $details['key']);
}

$releaseKey = makeRsaKeypair();
$riskKey = makeRsaKeypair();

$trustStore = array(
    'schema_version' => 1,
    'keys' => array(
        array(
            'key_id' => 'risk-test-key',
            'algorithm' => 'RSA-SHA256',
            'status' => 'active',
            'public_key_pem' => $riskKey['public'],
        ),
    ),
);

$trustPath = tempnam(sys_get_temp_dir(), 'p0trust');
$evidencePath = tempnam(sys_get_temp_dir(), 'p0risk');
file_put_contents($trustPath, json_encode($trustStore));
$trustSha = hash_file('sha256', $trustPath);

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

openssl_sign($payloadJson, $riskSignature, $riskKey['private'], OPENSSL_ALGO_SHA256);
file_put_contents($evidencePath, json_encode(array(
    'schema_version' => 1,
    'signed_payload' => base64_encode($payloadJson),
    'signature' => array(
        'algorithm' => 'RSA-SHA256',
        'key_id' => 'risk-test-key',
        'value' => base64_encode($riskSignature),
    ),
)));

$result = DkP0SecurityRiskEvidence::loadSigned(
    $evidencePath,
    $trustPath,
    $trustSha,
    new DateTimeImmutable('2026-10-07T00:00:00+00:00')
);
if ($result['evidence']['deployment_id'] !== 'deployment-test') {
    throw new RuntimeException('Trusted security evidence did not verify');
}

/*
 * Boundary test: the P0 release-report signing key is deliberately not in
 * the security-risk trust store. Possession of that key must not authorize
 * security evidence.
 */
openssl_sign($payloadJson, $releaseSignature, $releaseKey['private'], OPENSSL_ALGO_SHA256);
file_put_contents($evidencePath, json_encode(array(
    'schema_version' => 1,
    'signed_payload' => base64_encode($payloadJson),
    'signature' => array(
        'algorithm' => 'RSA-SHA256',
        'key_id' => 'release-signing-key',
        'value' => base64_encode($releaseSignature),
    ),
)));

try {
    DkP0SecurityRiskEvidence::loadSigned(
        $evidencePath,
        $trustPath,
        $trustSha,
        new DateTimeImmutable('2026-10-07T00:00:00+00:00')
    );
    throw new RuntimeException('Release signing key unexpectedly authorized security evidence');
} catch (RuntimeException $e) {
    // Expected: release signing identity is outside the evidence trust boundary.
}

@unlink($trustPath);
@unlink($evidencePath);
echo "P0 security signing boundary: PASS\n";
