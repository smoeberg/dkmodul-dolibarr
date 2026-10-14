<?php

/**
 * Fail-closed policy for controlled migration and decommissioning evidence.
 *
 * The application validates the evidence contract; it never performs a
 * destructive decommissioning operation itself.
 */
final class DkMigrationDecommissionPolicy
{
    public const SCHEMA_VERSION = '1';

    public static function validate(array $evidence, DateTimeImmutable $now): array
    {
        $required = array(
            'schemaVersion',
            'migrationId',
            'sourceSystem',
            'destinationSystem',
            'exportedAt',
            'verifiedAt',
            'exportSha256',
            'approvedBy',
            'cutoverAt',
            'decommissionAfter',
        );

        foreach ($required as $field) {
            if (!array_key_exists($field, $evidence) || trim((string) $evidence[$field]) === '') {
                throw new InvalidArgumentException('Migration evidence field is required: '.$field);
            }
        }

        if ((string) $evidence['schemaVersion'] !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported migration evidence schema version');
        }

        foreach (array('migrationId', 'sourceSystem', 'destinationSystem', 'approvedBy') as $field) {
            if (!is_string($evidence[$field])) {
                throw new InvalidArgumentException('Migration evidence field must be a string: '.$field);
            }
        }

        if ((string) $evidence['sourceSystem'] === (string) $evidence['destinationSystem']) {
            throw new InvalidArgumentException('Migration source and destination must differ');
        }

        if (!preg_match('/^[a-f0-9]{64}$/', strtolower((string) $evidence['exportSha256']))) {
            throw new InvalidArgumentException('Migration export SHA-256 is invalid');
        }

        $exportedAt = self::utcTimestamp((string) $evidence['exportedAt'], 'exportedAt');
        $verifiedAt = self::utcTimestamp((string) $evidence['verifiedAt'], 'verifiedAt');
        $cutoverAt = self::utcTimestamp((string) $evidence['cutoverAt'], 'cutoverAt');
        $decommissionAfter = self::utcTimestamp((string) $evidence['decommissionAfter'], 'decommissionAfter');

        if ($exportedAt > $verifiedAt) {
            throw new InvalidArgumentException('Migration verification cannot precede export');
        }
        if ($verifiedAt > $cutoverAt) {
            throw new InvalidArgumentException('Migration cutover cannot precede verification');
        }
        if ($cutoverAt > $decommissionAfter) {
            throw new InvalidArgumentException('Decommissioning must be scheduled after cutover');
        }
        if ($decommissionAfter <= $now) {
            throw new InvalidArgumentException('Decommissioning date must be in the future');
        }
        if ($exportedAt > $now || $verifiedAt > $now || $cutoverAt > $now) {
            throw new InvalidArgumentException('Migration evidence cannot contain future completed events');
        }

        return array(
            'migration_id' => (string) $evidence['migrationId'],
            'export_sha256' => strtolower((string) $evidence['exportSha256']),
            'decommission_after' => $decommissionAfter->format('Y-m-d\TH:i:s\Z'),
        );
    }

    private static function utcTimestamp(string $value, string $field): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value);
        if (!$date || $date->format('Y-m-d\TH:i:s\Z') !== $value) {
            throw new InvalidArgumentException('Migration '.$field.' must be UTC ISO-8601 without fractional seconds');
        }
        return $date;
    }
}
