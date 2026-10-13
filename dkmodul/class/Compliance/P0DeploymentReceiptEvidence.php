<?php

final class DkP0DeploymentReceiptEvidence
{
    public static function loadSigned($path, $trustStorePath, $expectedTrustStoreSha256, DateTimeImmutable $now)
    {
        $envelope = self::readJson($path, 'signed deployment receipt');
        if (!is_string($trustStorePath) || trim($trustStorePath) === '' || !is_readable($trustStorePath)) {
            throw new RuntimeException('A readable deployment receipt trust store is required');
        }
        $actualTrustStoreSha256 = hash_file('sha256', $trustStorePath);
        if (!is_string($expectedTrustStoreSha256) || !is_string($actualTrustStoreSha256)
            || !hash_equals($expectedTrustStoreSha256, $actualTrustStoreSha256)) {
            throw new RuntimeException('Deployment receipt trust store does not match the product manifest');
        }
        $trustStore = self::readJson($trustStorePath, 'attestation trust store');

        if (($envelope['schema_version'] ?? null) !== 1
            || !is_string($envelope['signed_payload'] ?? null)
            || !is_array($envelope['signature'] ?? null)) {
            throw new RuntimeException('Invalid signed deployment receipt envelope');
        }
        $signature = $envelope['signature'];
        if (($signature['algorithm'] ?? null) !== 'RSA-SHA256'
            || !is_string($signature['key_id'] ?? null)
            || !is_string($signature['value'] ?? null)) {
            throw new RuntimeException('Invalid deployment receipt signature metadata');
        }
        $payloadJson = base64_decode($envelope['signed_payload'], true);
        $signatureBytes = base64_decode($signature['value'], true);
        if ($payloadJson === false || $signatureBytes === false) {
            throw new RuntimeException('Deployment receipt contains invalid base64');
        }
        $publicKeyPem = self::trustedPublicKey($trustStore, $signature['key_id']);
        $publicKey = openssl_pkey_get_public($publicKeyPem);
        if ($publicKey === false || openssl_verify($payloadJson, $signatureBytes, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('Deployment receipt signature verification failed');
        }
        $payload = json_decode($payloadJson, true);
        if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Signed deployment receipt payload must be valid JSON');
        }
        self::assertValid($payload, $now);
        return array('evidence' => $payload, 'evidence_sha256' => hash('sha256', $payloadJson), 'path' => $path, 'key_id' => $signature['key_id']);
    }

    public static function assertMatchesAttestation(array $receipt, array $attestation, $deploymentId)
    {
        if (($receipt['deployment_id'] ?? null) !== $deploymentId || ($attestation['deployment_id'] ?? null) !== $deploymentId) {
            throw new RuntimeException('Deployment receipt does not match the deployment attestation');
        }
        if (($receipt['approved_by'] ?? null) !== ($attestation['approval']['approved_by'] ?? null)
            || ($receipt['valid_until'] ?? null) !== ($attestation['approval']['valid_until'] ?? null)) {
            throw new RuntimeException('Deployment receipt approval does not match the deployment attestation');
        }
    }

    private static function assertValid(array $payload, DateTimeImmutable $now)
    {
        foreach (array('deployment_id', 'environment', 'deployed_at', 'approved_by', 'approved_at', 'valid_until', 'change_reference') as $field) {
            if (!is_string($payload[$field] ?? null) || trim($payload[$field]) === '') {
                throw new RuntimeException('Deployment receipt field is required: '.$field);
            }
        }
        if (($payload['environment'] ?? '') !== 'production') {
            throw new RuntimeException('Deployment receipt environment must be production');
        }
        foreach (array('deployed_at', 'approved_at') as $field) {
            $date = self::timestamp($payload[$field]);
            if ($date === null || $date > $now) {
                throw new RuntimeException('Deployment receipt timestamp is invalid: '.$field);
            }
        }
        $approvedAt = self::date($payload['approved_at']);
        $validUntil = self::date($payload['valid_until']);
        if ($approvedAt === null || $validUntil === null || $approvedAt > $validUntil || $validUntil < $now->setTime(0, 0, 0)) {
            throw new RuntimeException('Deployment receipt approval period is invalid or expired');
        }
    }

    private static function timestamp($value)
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i:s\\Z', $value);
        return $date && $date->format('Y-m-d\\TH:i:s\\Z') === $value ? $date : null;
    }

    private static function date($value)
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }

    private static function trustedPublicKey(array $trustStore, $keyId)
    {
        if (($trustStore['schema_version'] ?? null) !== 1 || !is_array($trustStore['keys'] ?? null)) {
            throw new RuntimeException('Invalid attestation trust store');
        }
        foreach ($trustStore['keys'] as $key) {
            if (($key['key_id'] ?? null) === $keyId && ($key['algorithm'] ?? null) === 'RSA-SHA256'
                && ($key['status'] ?? null) === 'active' && is_string($key['public_key_pem'] ?? null)) {
                return $key['public_key_pem'];
            }
        }
        throw new RuntimeException('Deployment receipt signing key is not trusted or active');
    }

    private static function readJson($path, $label)
    {
        if (!is_string($path) || trim($path) === '' || !is_readable($path)) {
            throw new RuntimeException('A readable '.$label.' is required');
        }
        $data = json_decode(file_get_contents($path), true);
        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(ucfirst($label).' must be valid JSON');
        }
        return $data;
    }
}
