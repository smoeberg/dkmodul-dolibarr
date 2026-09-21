<?php

final class DkBackupComplianceStatus
{
    private const EEA_COUNTRIES = array(
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR',
        'GR', 'HU', 'IE', 'IS', 'IT', 'LI', 'LT', 'LU', 'LV', 'MT', 'NL', 'NO',
        'PL', 'PT', 'RO', 'SE', 'SI', 'SK',
    );

    public static function evaluate(array $backups, array $restores, DateTimeImmutable $now, $requiredRetainUntil, $hostingRegistration)
    {
        $reasons = array();
        $requiredRetention = self::date($requiredRetainUntil, 'required retention date');
        if (!self::nonEmpty($hostingRegistration)) {
            throw new RuntimeException('Hosting registration is required');
        }

        $latestFull = self::latestSuccessful($backups, 'full');
        $latestIncremental = self::latestSuccessful($backups, 'incremental');

        self::checkBackup($latestFull, 'full', $now, $now->modify('-8 days'), $requiredRetention, $hostingRegistration, $reasons);
        self::checkBackup($latestIncremental, 'incremental', $now, $now->modify('-2 days'), $requiredRetention, $hostingRegistration, $reasons);

        $latestRestore = self::latestPassedRestore($restores);
        if ($latestRestore === null) {
            $reasons[] = 'missing-passed-restore-test';
        } else {
            $completedAt = self::dateTime($latestRestore['completed_at'] ?? null, 'restore completed_at');
            if ($completedAt > $now) {
                $reasons[] = 'restore-test-future-dated';
            }
            if ($completedAt < $now->modify('-92 days')) {
                $reasons[] = 'restore-test-stale';
            }
            foreach (array('reviewed_by', 'reviewed_at', 'evidence_reference') as $field) {
                if (!self::nonEmpty($latestRestore[$field] ?? null)) {
                    $reasons[] = 'restore-evidence-missing-'.$field;
                }
            }
            foreach (array('database_sha256', 'document_sample_sha256', 'saft_sha256') as $field) {
                if (!preg_match('/^[a-f0-9]{64}$/', (string) ($latestRestore[$field] ?? ''))) {
                    $reasons[] = 'restore-evidence-invalid-'.$field;
                }
            }
            if (self::decimal8($latestRestore['debit_total'] ?? null) !== self::decimal8($latestRestore['credit_total'] ?? null)) {
                $reasons[] = 'restore-bookkeeping-unbalanced';
            }
        }

        return array('compliant' => count($reasons) === 0, 'reasons' => array_values(array_unique($reasons)));
    }

    private static function checkBackup($backup, $type, DateTimeImmutable $now, DateTimeImmutable $freshAfter, DateTimeImmutable $requiredRetention, $hostingRegistration, array &$reasons)
    {
        if ($backup === null) {
            $reasons[] = 'missing-successful-'.$type.'-backup';
            return;
        }
        $completedAt = self::dateTime($backup['completed_at'] ?? null, $type.' completed_at');
        if ($completedAt > $now) {
            $reasons[] = $type.'-backup-future-dated';
        }
        if ($completedAt < $freshAfter) {
            $reasons[] = $type.'-backup-stale';
        }
        if (!in_array(strtoupper((string) ($backup['country'] ?? '')), self::EEA_COUNTRIES, true)) {
            $reasons[] = $type.'-backup-outside-eu-eea';
        }
        if (($backup['provider_registration'] ?? '') === $hostingRegistration) {
            $reasons[] = $type.'-backup-not-independent';
        }
        if ((int) ($backup['signature_verified'] ?? 0) !== 1) {
            $reasons[] = $type.'-backup-receipt-unverified';
        }
        if (self::date($backup['retention_until'] ?? null, $type.' retention_until') < $requiredRetention
            || self::date($backup['immutable_until'] ?? null, $type.' immutable_until') < $requiredRetention) {
            $reasons[] = $type.'-backup-retention-insufficient';
        }
        if (!preg_match('/^[a-f0-9]{64}$/', (string) ($backup['receipt_sha256'] ?? ''))) {
            $reasons[] = $type.'-backup-receipt-hash-invalid';
        }
        foreach (array('provider_name', 'provider_registration', 'region', 'object_reference') as $field) {
            if (!self::nonEmpty($backup[$field] ?? null)) {
                $reasons[] = $type.'-backup-evidence-missing-'.$field;
            }
        }
        if ((int) ($backup['byte_size'] ?? 0) <= 0) {
            $reasons[] = $type.'-backup-byte-size-invalid';
        }
    }

    private static function latestSuccessful(array $rows, $type)
    {
        return self::latest($rows, static function ($row) use ($type) {
            return ($row['backup_type'] ?? null) === $type && ($row['status'] ?? null) === 'success';
        });
    }

    private static function latestPassedRestore(array $rows)
    {
        return self::latest($rows, static function ($row) {
            return ($row['status'] ?? null) === 'passed';
        });
    }

    private static function latest(array $rows, callable $accept)
    {
        $matching = array_values(array_filter($rows, $accept));
        usort($matching, static function ($left, $right) {
            return strcmp((string) ($right['completed_at'] ?? ''), (string) ($left['completed_at'] ?? ''));
        });

        return $matching[0] ?? null;
    }

    private static function date($value, $label)
    {
        $date = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new RuntimeException('Invalid '.$label);
        }
        return $date;
    }

    private static function dateTime($value, $label)
    {
        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException('Invalid '.$label);
        }
        try {
            return new DateTimeImmutable((string) $value);
        } catch (Throwable $e) {
            throw new RuntimeException('Invalid '.$label);
        }
    }

    private static function nonEmpty($value)
    {
        return is_string($value) && trim($value) !== '';
    }

    private static function decimal8($value)
    {
        $value = (string) $value;
        if (!preg_match('/^(-?)([0-9]+)(?:\.([0-9]{1,8}))?$/', $value, $matches)) {
            throw new RuntimeException('Invalid restore bookkeeping total');
        }
        $integer = ltrim($matches[2], '0');
        $fraction = str_pad($matches[3] ?? '', 8, '0');
        $normal = ($integer === '' ? '0' : $integer).$fraction;
        $normal = ltrim($normal, '0');
        if ($normal === '') {
            return '0';
        }

        return ($matches[1] === '-' ? '-' : '').$normal;
    }
}
