<?php

final class DkP0RiskEvidenceManifestBindingPolicy
{
    public static function assertMatches(array $manifest, array $riskEvidence)
    {
        if (!isset($manifest['items']) || !is_array($manifest['items'])) {
            throw new RuntimeException('Production evidence manifest items are required for risk evidence binding');
        }

        $items = array();
        foreach ($manifest['items'] as $item) {
            if (!is_array($item) || !is_string($item['controlId'] ?? null)) {
                continue;
            }
            $items[$item['controlId']] = $item;
        }

        self::assertHashBinding(
            $items,
            'formal-risk-register',
            $riskEvidence['risk_register_sha256'] ?? null,
            'risk register'
        );
        self::assertHashBinding(
            $items,
            'security-operational-controls',
            $riskEvidence['controls_sha256'] ?? null,
            'security controls'
        );

        return true;
    }

    private static function assertHashBinding(array $items, $controlId, $expectedHash, $label)
    {
        if (!isset($items[$controlId]) || ($items[$controlId]['status'] ?? null) !== 'evidenced') {
            throw new RuntimeException('Production evidence manifest '.$controlId.' must be evidenced for '.$label.' binding');
        }
        if (!is_string($expectedHash) || !preg_match('/^[a-f0-9]{64}$/', $expectedHash)) {
            throw new RuntimeException('Signed security risk evidence contains invalid '.$label.' fingerprint');
        }
        $manifestHash = $items[$controlId]['evidenceSha256'] ?? null;
        if (!is_string($manifestHash) || !preg_match('/^[a-f0-9]{64}$/', strtolower($manifestHash))) {
            throw new RuntimeException('Production evidence manifest '.$controlId.' requires a SHA-256 fingerprint');
        }
        if (!hash_equals(strtolower($expectedHash), strtolower($manifestHash))) {
            throw new RuntimeException('Production evidence manifest '.$controlId.' fingerprint does not match signed '.$label.' evidence');
        }
    }
}
