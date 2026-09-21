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

        $required = array(
            'deployment.provider',
            'deployment.primary_region',
            'deployment.backup_region',
            'deployment.storage_provider',
            'controls.restore_test',
            'controls.risk_assessment',
            'controls.processor_agreement',
        );

        foreach ($required as $path) {
            $value = trim((string) $this->value($path));
            if ($value === '' || strpos($value, 'TBD') === 0) {
                throw new RuntimeException('Unresolved registered-profile manifest value: '.$path);
            }
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
            'deployment.provider',
            'deployment.access_point.model',
            'controls.full_backup',
            'controls.incremental_backup',
            'controls.retention',
        );

        foreach ($required as $path) {
            $this->value($path);
        }

        if ((int) $this->value('schema_version') !== 1) {
            throw new RuntimeException('Unsupported product manifest schema version');
        }
    }
}
