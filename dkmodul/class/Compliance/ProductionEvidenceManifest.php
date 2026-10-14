<?php

/**
 * Fail-closed index for production evidence that cannot be created by the
 * application itself.
 *
 * The manifest records the evidence reference and cryptographic fingerprint;
 * it never treats a missing reference as compliant.
 */
final class DkProductionEvidenceManifest
{
    public const SCHEMA_VERSION = '1';

    public static function validate(array $manifest): array
    {
        if (($manifest['schemaVersion'] ?? null) !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported production evidence manifest schema');
        }

        if (!isset($manifest['deploymentId']) || !is_string($manifest['deploymentId']) || trim($manifest['deploymentId']) === '') {
            throw new InvalidArgumentException('Production evidence manifest deploymentId is required');
        }

        if (!isset($manifest['items']) || !is_array($manifest['items'])) {
            throw new InvalidArgumentException('Production evidence manifest items are required');
        }

        $seen = array();
        foreach ($manifest['items'] as $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('Production evidence manifest item is invalid');
            }
            foreach (array('controlId', 'status') as $field) {
                if (!is_string($item[$field] ?? null) || trim($item[$field]) === '') {
                    throw new InvalidArgumentException('Production evidence item requires '.$field);
                }
            }
            $controlId = $item['controlId'];
            if (isset($seen[$controlId])) {
                throw new InvalidArgumentException('Duplicate production evidence control: '.$controlId);
            }
            $seen[$controlId] = true;

            if (!in_array($item['status'], array('open', 'evidenced'), true)) {
                throw new InvalidArgumentException('Invalid production evidence status: '.$item['status']);
            }

            if ($item['status'] === 'evidenced') {
                if (!is_string($item['evidenceRef'] ?? null) || trim($item['evidenceRef']) === '') {
                    throw new InvalidArgumentException('Evidenced control requires evidenceRef: '.$controlId);
                }
                if (!preg_match('/^[a-f0-9]{64}$/', strtolower((string) ($item['evidenceSha256'] ?? '')))) {
                    throw new InvalidArgumentException('Evidenced control requires SHA-256: '.$controlId);
                }
                if (!is_string($item['approvedBy'] ?? null) || trim($item['approvedBy']) === '') {
                    throw new InvalidArgumentException('Evidenced control requires approvedBy: '.$controlId);
                }
                if (!is_string($item['approvedAt'] ?? null) || trim($item['approvedAt']) === '') {
                    throw new InvalidArgumentException('Evidenced control requires approvedAt: '.$controlId);
                }
            }
        }

        return array(
            'deployment_id' => $manifest['deploymentId'],
            'evidenced_count' => count(array_filter($manifest['items'], function ($item) {
                return $item['status'] === 'evidenced';
            })),
            'open_count' => count(array_filter($manifest['items'], function ($item) {
                return $item['status'] === 'open';
            })),
        );
    }

    public static function assertComplete(array $manifest): array
    {
        $result = self::validate($manifest);
        if ($result['open_count'] !== 0) {
            throw new RuntimeException('Production evidence manifest is incomplete');
        }
        return $result;
    }
}
