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
