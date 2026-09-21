<?php

final class DkProductManifest
{
    private $data;

    private function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function load($path)
    {
        $json = @file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException('Unable to read Dolibarr DK product manifest');
        }

        $data = json_decode($json, true);
        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid Dolibarr DK product manifest JSON');
        }

        $manifest = new self($data);
        $manifest->assertStructure();

        return $manifest;
    }

    public function value($path)
    {
        $value = $this->data;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                throw new RuntimeException('Missing product manifest value: '.$path);
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function assertRegistrable()
    {
        if ($this->value('product.profile_status') !== 'registered-candidate') {
            throw new RuntimeException('Product manifest is not a registered-candidate profile');
        }

        if ($this->value('deployment.hosting_policy') !== 'customer-selectable-with-deployment-attestation') {
            throw new RuntimeException('Unsupported registered-profile hosting policy');
        }
        if ($this->value('deployment.deployment_attestation.required') !== true) {
            throw new RuntimeException('Deployment attestation must be required');
        }
        if ($this->value('deployment.deployment_attestation.signature_required') !== true
            || $this->value('deployment.deployment_attestation.signature_algorithms') !== array('RSA-SHA256')) {
            throw new RuntimeException('Registered profile requires RSA-SHA256 signed deployment attestations');
        }
        if (!preg_match('/^[a-f0-9]{64}$/', $this->value('deployment.deployment_attestation.trust_store_sha256'))) {
            throw new RuntimeException('Registered profile requires a pinned attestation trust-store SHA-256');
        }
        if ($this->value('deployment.backup_policy.eu_eea_copy_required') !== true) {
            throw new RuntimeException('An EU/EEA backup copy must be required');
        }
    }

    private function assertStructure()
    {
        $required = array(
            'schema_version',
            'product.id',
            'product.release',
            'product.profile_status',
            'components.dolibarr',
            'components.dk_module',
            'components.php',
            'components.database',
            'components.saft',
            'components.oioubl',
            'deployment.model',
            'deployment.hosting_policy',
            'deployment.deployment_attestation.required',
            'deployment.deployment_attestation.schema',
            'deployment.deployment_attestation.signature_required',
            'deployment.deployment_attestation.signature_algorithms',
            'deployment.deployment_attestation.trust_store_schema',
            'deployment.deployment_attestation.trust_store_sha256',
            'deployment.backup_policy.third_party_copy_required',
            'deployment.backup_policy.eu_eea_copy_required',
            'deployment.backup_policy.provider_must_be_identified_per_deployment',
            'deployment.access_point.model',
            'controls.full_backup',
            'controls.incremental_backup',
            'controls.retention',
            'controls.restore_test_required',
            'controls.risk_assessment_required',
        );

        foreach ($required as $path) {
            $this->value($path);
        }

        if ((int) $this->value('schema_version') !== 1) {
            throw new RuntimeException('Unsupported product manifest schema version');
        }
    }
}
