<?php

final class DkDeploymentAttestationManifestBindingPolicy
{
    public static function assertMatches(array $manifest, array $attestation, $expectedDeploymentId)
    {
        if (!isset($manifest['items']) || !is_array($manifest['items'])) {
            throw new RuntimeException('Production evidence manifest items are required for deployment attestation binding');
        }

        $item = null;
        foreach ($manifest['items'] as $candidate) {
            if (is_array($candidate) && ($candidate['controlId'] ?? null) === 'production-deployment-attestation') {
                $item = $candidate;
                break;
            }
        }

        if ($item === null || ($item['status'] ?? null) !== 'evidenced') {
            throw new RuntimeException('Production deployment attestation manifest control must be evidenced');
        }
        if (!is_string($expectedDeploymentId) || trim($expectedDeploymentId) === ''
            || ($attestation['deployment_id'] ?? null) !== $expectedDeploymentId) {
            throw new RuntimeException('Signed deployment attestation deployment id does not match release deployment');
        }

        $manifestHash = $item['evidenceSha256'] ?? null;
        if (!is_string($manifestHash) || !preg_match('/^[a-f0-9]{64}$/', strtolower($manifestHash))) {
            throw new RuntimeException('Production deployment attestation manifest control requires a SHA-256 fingerprint');
        }

        $attestationHash = hash('sha256', DkP0ReleaseGate::canonicalJson($attestation));
        if (!hash_equals(strtolower($manifestHash), $attestationHash)) {
            throw new RuntimeException('Production deployment attestation fingerprint does not match signed attestation');
        }

        return true;
    }
}
