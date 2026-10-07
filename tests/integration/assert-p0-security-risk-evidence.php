<?php

require_once __DIR__.'/../../dkmodul/class/Compliance/P0SecurityRiskEvidence.php';

$key = openssl_pkey_new(array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA));
if ($key === false || !openssl_pkey_export($key, $private)) {
    throw new RuntimeException('Unable to generate test RSA key');
}
$details = openssl_pkey_get_details($key);
$trustStore = array(
    'schema_version' => 1,
    'keys' => array(
        array('key_id' => 'risk-test-key', 'algorithm' => 'RSA-SHA256', 'status' => 'active', 'public_key_pem' => $details['key']),
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
openssl_sign($payloadJson, $signature, $private, OPENSSL_ALGO_SHA256);
file_put_contents($evidencePath, json_encode(array(
    'schema_version' => 1,
    'signed_payload' => base64_encode($payloadJson),
    'signature' => array('algorithm' => 'RSA-SHA256', 'key_id' => 'risk-test-key', 'value' => base64_encode($signature)),
)));

$result = DkP0SecurityRiskEvidence::loadSigned($evidencePath, $trustPath, $trustSha, new DateTimeImmutable('2026-10-07T00:00:00+00:00'));
if ($result['evidence']['deployment_id'] !== 'deployment-test' || !preg_match('/^[a-f0-9]{64}$/', $result['evidence_sha256'])) {
    throw new RuntimeException('Security risk evidence verification failed');
}

$payload['status'] = 'rejected';
$badJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
file_put_contents($evidencePath, json_encode(array(
    'schema_version' => 1,
    'signed_payload' => base64_encode($badJson),
    'signature' => array('algorithm' => 'RSA-SHA256', 'key_id' => 'risk-test-key', 'value' => base64_encode($signature)),
)));
try {
    DkP0SecurityRiskEvidence::loadSigned($evidencePath, $trustPath, $trustSha, new DateTimeImmutable('2026-10-07T00:00:00+00:00'));
    throw new RuntimeException('Tampered security risk evidence unexpectedly verified');
} catch (RuntimeException $e) {
    // Expected.
}

@unlink($trustPath);
@unlink($evidencePath);
echo "P0 security risk evidence: PASS\n";
