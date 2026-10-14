<?php

/**
 * Final fail-closed boundary before a P0 release may be represented as
 * registered-candidate.
 *
 * This does not create production evidence. It only proves that the complete
 * signed P0 report is eligible for the status transition.
 */
final class DkP0ReleaseCandidatePolicy
{
    public static function assertEligible(array $report, array $signature, DkP0ReleaseSigningKeyProvider $provider)
    {
        if (($report['decision'] ?? null) !== 'PASS') {
            throw new RuntimeException('P0 release cannot become registered-candidate unless all gates pass');
        }

        if (!isset($report['gates']) || !is_array($report['gates'])) {
            throw new RuntimeException('P0 release report gates are required');
        }

        $required = array(
            'product-manifest',
            'deployment-attestation',
            'backup-retention-restore',
            'compliance-monitoring',
            'access-point-certification',
            'security-risk-evidence',
        );
        $seen = array();
        foreach ($report['gates'] as $gate) {
            if (!is_array($gate) || !isset($gate['gate_id'], $gate['status'])) {
                throw new RuntimeException('P0 release report contains an invalid gate');
            }
            $id = (string) $gate['gate_id'];
            if (isset($seen[$id])) {
                throw new RuntimeException('P0 release report contains duplicate gate: '.$id);
            }
            $seen[$id] = true;
            if (in_array($id, $required, true) && $gate['status'] !== 'PASS') {
                throw new RuntimeException('Required P0 release gate is not PASS: '.$id);
            }
        }

        foreach ($required as $id) {
            if (!isset($seen[$id])) {
                throw new RuntimeException('Required P0 release gate is missing: '.$id);
            }
        }

        if (!isset($signature['key_id']) || $signature['key_id'] !== $provider->keyId()) {
            throw new RuntimeException('P0 release signature key does not match configured provider');
        }
        DkP0ReleaseSigningPolicy::assertSignatureMatchesProvider($report, $signature, $provider);

        return true;
    }
}
