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
                if (!self::isUtcTimestamp($item['approvedAt'] ?? null)) {
                    throw new InvalidArgumentException('Evidenced control requires valid UTC approvedAt: '.$controlId);
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

    private static function isUtcTimestamp($value): bool
    {
        if (!is_string($value) || !preg_match('/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}Z$/', $value)) {
            return false;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i:s\\Z', $value, new DateTimeZone('UTC'));
        return $date !== false && $date->format('Y-m-d\\TH:i:s\\Z') === $value;
    }

    public static function loadJson($path, $expectedDeploymentId = null): array
    {
        if (!is_string($path) || trim($path) === '' || !is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Production evidence manifest is missing or unreadable');
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException('Production evidence manifest cannot be read');
        }

        try {
            $manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw new RuntimeException('Production evidence manifest JSON is invalid', 0, $e);
        }
        if (!is_array($manifest)) {
            throw new RuntimeException('Production evidence manifest must be a JSON object');
        }

        $result = self::assertComplete($manifest);
        if ($expectedDeploymentId !== null && $result['deployment_id'] !== $expectedDeploymentId) {
            throw new RuntimeException('Production evidence manifest deployment mismatch');
        }
        return array('manifest' => $manifest, 'summary' => $result, 'sha256' => hash('sha256', $json));
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
