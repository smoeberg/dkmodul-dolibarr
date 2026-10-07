<?php

final class DkP0SecurityRiskEvidence
{
    public static function loadSigned($path, $trustStorePath, $expectedTrustStoreSha256, DateTimeImmutable $now)
    {
        $envelope = self::readJson($path, 'security risk evidence');
        if (!is_readable($trustStorePath)) {
            throw new RuntimeException('A readable security evidence trust store is required');
        }
        $actualTrustStoreSha256 = hash_file('sha256', $trustStorePath);
        if (!is_string($expectedTrustStoreSha256) || !is_string($actualTrustStoreSha256)
            || !hash_equals($expectedTrustStoreSha256, $actualTrustStoreSha256)) {
            throw new RuntimeException('Security evidence trust store does not match the product manifest');
        }
        $trustStore = self::readJson($trustStorePath, 'security evidence trust store');

        if (($envelope['schema_version'] ?? null) !== 1
            || !is_string($envelope['signed_payload'] ?? null)
            || !is_array($envelope['signature'] ?? null)) {
            throw new RuntimeException('Invalid signed security risk evidence envelope');
        }

        $signature = $envelope['signature'];
        if (($signature['algorithm'] ?? null) !== 'RSA-SHA256'
            || !is_string($signature['key_id'] ?? null)
            || !is_string($signature['value'] ?? null)) {
            throw new RuntimeException('Invalid security risk evidence signature metadata');
        }

        $payloadJson = base64_decode($envelope['signed_payload'], true);
        $signatureBytes = base64_decode($signature['value'], true);
        if ($payloadJson === false || $signatureBytes === false) {
            throw new RuntimeException('Security risk evidence contains invalid base64');
        }

        $publicKeyPem = self::trustedPublicKey($trustStore, $signature['key_id']);
        $publicKey = openssl_pkey_get_public($publicKeyPem);
        if ($publicKey === false || openssl_verify($payloadJson, $signatureBytes, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('Security risk evidence signature verification failed');
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Signed security risk evidence payload must be valid JSON');
        }
        self::assertValid($payload, $now);

        return array(
            'evidence' => $payload,
            'evidence_sha256' => hash('sha256', $payloadJson),
            'signer_key_id' => $signature['key_id'],
        );
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

    private static function trustedPublicKey(array $trustStore, $keyId)
    {
        if (($trustStore['schema_version'] ?? null) !== 1 || !is_array($trustStore['keys'] ?? null)) {
            throw new RuntimeException('Invalid security evidence trust store');
        }
        foreach ($trustStore['keys'] as $key) {
            if (($key['key_id'] ?? null) === $keyId
                && ($key['algorithm'] ?? null) === 'RSA-SHA256'
                && ($key['status'] ?? null) === 'active'
                && is_string($key['public_key_pem'] ?? null)) {
                return $key['public_key_pem'];
            }
        }

        throw new RuntimeException('Security evidence signing key is not trusted or active');
    }

    private static function assertValid(array $data, DateTimeImmutable $now)
    {
        if (($data['schema_version'] ?? null) !== 1
            || ($data['status'] ?? null) !== 'approved'
            || !is_string($data['deployment_id'] ?? null)
            || trim($data['deployment_id']) === ''
            || !is_string($data['reviewer'] ?? null)
            || trim($data['reviewer']) === '') {
            throw new RuntimeException('Security risk evidence is not an approved deployment assessment');
        }

        foreach (array('assessed_at', 'valid_until') as $field) {
            if (!is_string($data[$field] ?? null)) {
                throw new RuntimeException('Security risk evidence requires '.$field);
            }
        }
        try {
            $assessedAt = new DateTimeImmutable($data['assessed_at']);
            $validUntil = new DateTimeImmutable($data['valid_until']);
        } catch (Throwable $e) {
            throw new RuntimeException('Security risk evidence contains invalid dates');
        }
        if ($assessedAt > $now || $validUntil < $now || $validUntil < $assessedAt) {
            throw new RuntimeException('Security risk evidence approval period is invalid or expired');
        }

        foreach (array('risk_register_sha256', 'controls_sha256') as $field) {
            if (!preg_match('/^[a-f0-9]{64}$/', (string) ($data[$field] ?? ''))) {
                throw new RuntimeException('Security risk evidence requires '.$field);
            }
        }
    }
}
