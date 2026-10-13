<?php

final class DkDeploymentAttestation
{
    private const EEA_COUNTRIES = array(
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR',
        'GR', 'HU', 'IE', 'IS', 'IT', 'LI', 'LT', 'LU', 'LV', 'MT', 'NL', 'NO',
        'PL', 'PT', 'RO', 'SE', 'SI', 'SK',
    );

    public static function load($path)
    {
        if (!is_string($path) || trim($path) === '' || !is_readable($path)) {
            throw new RuntimeException('A readable deployment attestation is required for the registered profile');
        }

        $data = json_decode(file_get_contents($path), true);
        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Deployment attestation must be valid JSON');
        }

        self::assertValid($data);

        return $data;
    }

    public static function loadSigned($path, $trustStorePath, $expectedTrustStoreSha256)
    {
        $envelope = self::readJson($path, 'signed deployment attestation');
        if (!is_string($trustStorePath) || trim($trustStorePath) === '' || !is_readable($trustStorePath)) {
            throw new RuntimeException('A readable attestation trust store is required for the registered profile');
        }
        $actualTrustStoreSha256 = hash_file('sha256', $trustStorePath);
        if (!is_string($expectedTrustStoreSha256) || !is_string($actualTrustStoreSha256)
            || !hash_equals($expectedTrustStoreSha256, $actualTrustStoreSha256)) {
            throw new RuntimeException('Attestation trust store does not match the product manifest');
        }
        $trustStore = self::readJson($trustStorePath, 'attestation trust store');

        if (($envelope['schema_version'] ?? null) !== 1
            || !is_string($envelope['signed_payload'] ?? null)
            || !is_array($envelope['signature'] ?? null)) {
            throw new RuntimeException('Invalid signed deployment attestation envelope');
        }

        $signature = $envelope['signature'];
        if (($signature['algorithm'] ?? null) !== 'RSA-SHA256'
            || !is_string($signature['key_id'] ?? null)
            || !is_string($signature['value'] ?? null)) {
            throw new RuntimeException('Invalid deployment attestation signature metadata');
        }

        $payloadJson = base64_decode($envelope['signed_payload'], true);
        $signatureBytes = base64_decode($signature['value'], true);
        if ($payloadJson === false || $signatureBytes === false) {
            throw new RuntimeException('Deployment attestation contains invalid base64');
        }

        $publicKeyPem = self::trustedPublicKey($trustStore, $signature['key_id'], $signature['algorithm']);
        $publicKey = openssl_pkey_get_public($publicKeyPem);
        if ($publicKey === false || openssl_verify($payloadJson, $signatureBytes, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('Deployment attestation signature verification failed');
        }

        $data = json_decode($payloadJson, true);
        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Signed deployment attestation payload must be valid JSON');
        }
        self::assertValid($data);
        self::assertApprovalValid($data);

        return $data;
    }

    private static function readJson($path, $label)
    {
        if (!is_string($path) || trim($path) === '' || !is_readable($path)) {
            throw new RuntimeException('A readable '.$label.' is required for the registered profile');
        }
        $data = json_decode(file_get_contents($path), true);
        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(ucfirst($label).' must be valid JSON');
        }

        return $data;
    }

    private static function trustedPublicKey(array $trustStore, $keyId, $algorithm)
    {
        if (($trustStore['schema_version'] ?? null) !== 1 || !is_array($trustStore['keys'] ?? null)) {
            throw new RuntimeException('Invalid attestation trust store');
        }
        foreach ($trustStore['keys'] as $key) {
            if (($key['key_id'] ?? null) === $keyId
                && ($key['algorithm'] ?? null) === $algorithm
                && ($key['status'] ?? null) === 'active'
                && is_string($key['public_key_pem'] ?? null)) {
                return $key['public_key_pem'];
            }
        }

        throw new RuntimeException('Deployment attestation signing key is not trusted or active');
    }

    private static function assertApprovalValid(array $data)
    {
        foreach (array('approval.approved_by', 'approval.approved_at', 'approval.valid_until') as $field) {
            if (!self::hasNonEmptyString($data, $field)) {
                throw new RuntimeException('Deployment attestation field is required: '.$field);
            }
        }
        $approvedAt = DateTimeImmutable::createFromFormat('!Y-m-d', self::valueAt($data, 'approval.approved_at'));
        $validUntil = DateTimeImmutable::createFromFormat('!Y-m-d', self::valueAt($data, 'approval.valid_until'));
        $today = new DateTimeImmutable('today');
        if (!$approvedAt || !$validUntil
            || $approvedAt->format('Y-m-d') !== self::valueAt($data, 'approval.approved_at')
            || $validUntil->format('Y-m-d') !== self::valueAt($data, 'approval.valid_until')
            || $approvedAt > $today || $validUntil < $today || $validUntil < $approvedAt) {
            throw new RuntimeException('Deployment attestation approval period is invalid or expired');
        }
    }

    private static function assertValid(array $data)
    {
        if (($data['schema_version'] ?? null) !== 1) {
            throw new RuntimeException('Unsupported deployment attestation schema version');
        }

        foreach (array(
            'deployment_id',
            'hosting.legal_name', 'hosting.registration_number', 'hosting.country',
            'backup.legal_name', 'backup.registration_number', 'backup.country',
            'backup.full_schedule', 'backup.incremental_schedule',
            'evidence.risk_assessment', 'evidence.restore_test',
            'evidence.retention_test', 'evidence.authority_export_test',
        ) as $field) {
            if (!self::hasNonEmptyString($data, $field)) {
                throw new RuntimeException('Deployment attestation field is required: '.$field);
            }
        }

        foreach (array('hosting.regions', 'backup.regions') as $field) {
            $value = self::valueAt($data, $field);
            if (!is_array($value) || count($value) === 0) {
                throw new RuntimeException('Deployment attestation field must be a non-empty array: '.$field);
            }
        }

        $backupCountry = strtoupper(self::valueAt($data, 'backup.country'));
        if (!in_array($backupCountry, self::EEA_COUNTRIES, true)) {
            throw new RuntimeException('The independent backup copy must be located in the EU/EEA');
        }

        if (self::valueAt($data, 'hosting.registration_number') === self::valueAt($data, 'backup.registration_number')) {
            throw new RuntimeException('The backup copy must be operated by an independent third party');
        }

        if (!in_array(strtolower(self::valueAt($data, 'backup.full_schedule')), array('daily', 'weekly'), true)) {
            throw new RuntimeException('Full backup schedule must be daily or weekly');
        }
        if (!in_array(strtolower(self::valueAt($data, 'backup.incremental_schedule')), array('hourly', 'daily'), true)) {
            throw new RuntimeException('Incremental backup schedule must be hourly or daily');
        }
    }

    private static function hasNonEmptyString(array $data, $path)
    {
        $value = self::valueAt($data, $path);

        return is_string($value) && trim($value) !== '';
    }

    private static function valueAt(array $data, $path)
    {
        $value = $data;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
