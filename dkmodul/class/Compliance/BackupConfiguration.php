<?php

require_once __DIR__.'/DeploymentAttestation.php';
require_once __DIR__.'/ProductManifest.php';

/**
 * Resolves the per-installation backup configuration for the registered
 * profile. Every value originates from the signed deployment attestation
 * (or the module constants that locate it). Nothing is hardcoded: the
 * platform, provider, destination region and schedules are decided per
 * deployment and proven by the signed attestation.
 */
final class DkBackupConfiguration
{
    /**
     * @param array $settings Map of module constant names to their current values:
     *   DKMODUL_COMPLIANCE_MODE, DKMODUL_DEPLOYMENT_ATTESTATION_PATH,
     *   DKMODUL_ATTESTATION_TRUST_STORE_PATH, DKMODUL_DEPLOYMENT_ID,
     *   DKMODUL_REQUIRED_RETAIN_UNTIL, DKMODUL_HOSTING_REGISTRATION.
     * @param string $manifestPath Absolute path to product-manifest.json.
     */
    public static function resolve(array $settings, $manifestPath)
    {
        if (empty($settings['DKMODUL_COMPLIANCE_MODE'])) {
            throw new RuntimeException('Danish compliance mode is disabled; backup configuration is not required');
        }

        $manifest = DkProductManifest::load($manifestPath);
        $attestationPath = $settings['DKMODUL_DEPLOYMENT_ATTESTATION_PATH'] ?? '';
        $trustStorePath = $settings['DKMODUL_ATTESTATION_TRUST_STORE_PATH'] ?? '';
        $expectedTrustStoreSha256 = $manifest->value('deployment.deployment_attestation.trust_store_sha256');

        $attestation = DkDeploymentAttestation::loadSigned($attestationPath, $trustStorePath, $expectedTrustStoreSha256);

        $deploymentId = (string) ($settings['DKMODUL_DEPLOYMENT_ID'] ?? '');
        $attestedDeploymentId = (string) ($attestation['deployment_id'] ?? '');
        if ($deploymentId === '' || $attestedDeploymentId === '' || !hash_equals($attestedDeploymentId, $deploymentId)) {
            throw new RuntimeException('Deployment identifier does not match the signed deployment attestation');
        }

        $hostingRegistration = (string) ($settings['DKMODUL_HOSTING_REGISTRATION'] ?? '');
        $attestedHostingRegistration = (string) ($attestation['hosting']['registration_number'] ?? '');
        if ($hostingRegistration === '' || $attestedHostingRegistration === ''
            || !hash_equals($attestedHostingRegistration, $hostingRegistration)) {
            throw new RuntimeException('Hosting registration number does not match the signed deployment attestation');
        }

        return array(
            'deployment_id' => $deploymentId,
            'required_retain_until' => (string) ($settings['DKMODUL_REQUIRED_RETAIN_UNTIL'] ?? ''),
            'hosting' => $attestation['hosting'],
            'backup' => $attestation['backup'],
            'approval' => $attestation['approval'],
            'attestation_path' => $attestationPath,
            'trust_store_path' => $trustStorePath,
        );
    }

    /**
     * Human-readable summary of the resolved backup configuration, for the
     * module admin screen. Values are echoed from the attestation only.
     */
    public static function summarize(array $configuration)
    {
        $backup = $configuration['backup'];

        return array(
            'provider' => $backup['legal_name'],
            'provider_registration' => $backup['registration_number'],
            'provider_country' => strtoupper($backup['country']),
            'regions' => implode(', ', $backup['regions']),
            'full_schedule' => $backup['full_schedule'],
            'incremental_schedule' => $backup['incremental_schedule'],
            'approved_by' => $configuration['approval']['approved_by'],
            'approved_at' => $configuration['approval']['approved_at'],
            'valid_until' => $configuration['approval']['valid_until'],
        );
    }
}
