<?php

final class DkP0ReleaseGate
{
    private const REQUIRED_GATES = array(
        'product-manifest',
        'deployment-attestation',
        'backup-retention-restore',
        'compliance-monitoring',
        'access-point-certification',
        'security-risk-evidence',
    );

    public static function evaluate($productId, $release, $deploymentId, array $results, DateTimeImmutable $generatedAt)
    {
        foreach (array('product_id' => $productId, 'release' => $release, 'deployment_id' => $deploymentId) as $field => $value) {
            if (!is_string($value) || trim($value) === '') {
                throw new RuntimeException('P0 release report requires '.$field);
            }
        }

        $indexed = array();
        foreach ($results as $result) {
            if (!is_array($result) || !isset($result['gate_id'])) {
                throw new RuntimeException('P0 release gate result requires gate_id');
            }
            $gateId = (string) $result['gate_id'];
            if (!in_array($gateId, self::REQUIRED_GATES, true)) {
                throw new RuntimeException('Unknown P0 release gate: '.$gateId);
            }
            if (isset($indexed[$gateId])) {
                throw new RuntimeException('Duplicate P0 release gate: '.$gateId);
            }
            $indexed[$gateId] = self::normaliseResult($gateId, $result);
        }

        $gates = array();
        foreach (self::REQUIRED_GATES as $gateId) {
            $gates[] = $indexed[$gateId] ?? array(
                'gate_id' => $gateId,
                'status' => 'BLOCKED',
                'evidence_sha256' => null,
                'reason_codes' => array('missing-gate-result'),
            );
        }

        $decision = 'PASS';
        foreach ($gates as $gate) {
            if ($gate['status'] === 'FAIL') {
                $decision = 'FAIL';
                break;
            }
            if ($gate['status'] === 'BLOCKED') {
                $decision = 'BLOCKED';
            }
        }

        $payload = array(
            'schema_version' => 1,
            'report_type' => 'dolibarr-dk-p0-release-gate',
            'product_id' => trim($productId),
            'release' => trim($release),
            'deployment_id' => trim($deploymentId),
            'generated_at' => $generatedAt->format(DateTimeInterface::ATOM),
            'decision' => $decision,
            'gates' => $gates,
        );
        $payload['report_sha256'] = hash('sha256', self::canonicalJson($payload));

        return $payload;
    }

    public static function canonicalJson(array $value)
    {
        $json = json_encode(self::sortRecursively($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            throw new RuntimeException('Unable to encode canonical P0 release report JSON');
        }

        return $json;
    }

    private static function normaliseResult($gateId, array $result)
    {
        $status = strtoupper((string) ($result['status'] ?? ''));
        if (!in_array($status, array('PASS', 'FAIL', 'BLOCKED'), true)) {
            throw new RuntimeException('Invalid P0 release gate status for '.$gateId);
        }
        $evidence = $result['evidence_sha256'] ?? null;
        if ($status === 'PASS' && !preg_match('/^[a-f0-9]{64}$/', (string) $evidence)) {
            throw new RuntimeException('Passing P0 release gate requires evidence SHA-256: '.$gateId);
        }
        if ($evidence !== null && !preg_match('/^[a-f0-9]{64}$/', (string) $evidence)) {
            throw new RuntimeException('Invalid P0 release gate evidence SHA-256: '.$gateId);
        }

        $reasons = $result['reason_codes'] ?? array();
        if (!is_array($reasons)) {
            throw new RuntimeException('P0 release gate reason_codes must be an array: '.$gateId);
        }
        $reasons = array_values(array_unique(array_map('strval', $reasons)));
        sort($reasons, SORT_STRING);
        if ($status === 'PASS' && count($reasons) > 0) {
            throw new RuntimeException('Passing P0 release gate cannot contain reason codes: '.$gateId);
        }
        if ($status !== 'PASS' && count($reasons) === 0) {
            throw new RuntimeException('Non-passing P0 release gate requires a reason code: '.$gateId);
        }

        return array(
            'gate_id' => $gateId,
            'status' => $status,
            'evidence_sha256' => $evidence,
            'reason_codes' => $reasons,
        );
    }

    private static function sortRecursively($value)
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_keys($value) !== range(0, count($value) - 1)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $item) {
            $value[$key] = self::sortRecursively($item);
        }

        return $value;
    }
}
