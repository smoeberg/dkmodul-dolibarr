<?php

final class DkComplianceLock
{
    public static function triggerDecision($moduleEnabled, $registeredProfile, $complianceMode)
    {
        if (!$moduleEnabled) {
            return 'module-disabled';
        }

        if ($registeredProfile && !$complianceMode) {
            throw new RuntimeException('DK compliance mode cannot be disabled in the registered profile');
        }

        return $complianceMode ? 'enforce' : 'development-bypass';
    }

    public static function assertRemovalAllowed($registeredProfile)
    {
        if ($registeredProfile) {
            throw new RuntimeException('The DK compliance module cannot be removed from a registered profile');
        }
    }

    public static function assertManifestReady($manifestPath, $registeredProfile)
    {
        return self::assertDeploymentReady($manifestPath, '', $registeredProfile);
    }

    public static function assertDeploymentReady($manifestPath, $attestationPath, $registeredProfile, $trustStorePath = '')
    {
        require_once __DIR__.'/ProductManifest.php';
        $manifest = DkProductManifest::load($manifestPath);
        if ($registeredProfile) {
            $manifest->assertRegistrable();
            require_once __DIR__.'/DeploymentAttestation.php';
            DkDeploymentAttestation::loadSigned(
                $attestationPath,
                $trustStorePath,
                $manifest->value('deployment.deployment_attestation.trust_store_sha256')
            );
        }

        return $manifest;
    }
}
