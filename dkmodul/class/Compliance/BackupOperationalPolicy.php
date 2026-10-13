<?php

final class DkBackupOperationalPolicy
{
    public static function validate(array $backup, DateTimeImmutable $now, $requiredRetainUntil, array $evidence = null)
    {
        $reasons = array();

        if (!self::hasWeeklySchedule($backup['full_schedule'] ?? null)) {
            $reasons[] = 'full-backup-schedule-not-weekly';
        }
        if (!self::hasDailySchedule($backup['incremental_schedule'] ?? null)) {
            $reasons[] = 'incremental-backup-schedule-not-daily';
        }

        $required = self::date($requiredRetainUntil);
        if ($required < $now->modify('+5 years')->setTime(0, 0, 0)) {
            $reasons[] = 'required-retention-less-than-five-years';
        }

        foreach (array('legal_name', 'registration_number', 'country', 'regions') as $field) {
            if (!isset($backup[$field]) || $backup[$field] === '' || (is_array($backup[$field]) && count($backup[$field]) === 0)) {
                $reasons[] = 'backup-configuration-missing-'.$field;
            }
        }

        if (!isset($backup['regions']) || !is_array($backup['regions']) || count($backup['regions']) === 0) {
            $reasons[] = 'backup-regions-missing';
        }

        if ($evidence === null) {
            $reasons[] = 'backup-evidence-attestation-missing';
        } else {
            self::validateQuarterlyRestore($evidence['quarterly_restore_test'] ?? null, $now, $reasons);
            self::validateCustomerExit($evidence['customer_exit_access_test'] ?? null, $now, $reasons);
        }

        return array_values(array_unique($reasons));
    }

    private static function validateQuarterlyRestore($evidence, DateTimeImmutable $now, array &$reasons)
    {
        if (!is_array($evidence)) {
            $reasons[] = 'quarterly-restore-evidence-missing';
            return;
        }
        if (($evidence['status'] ?? null) !== 'passed') {
            $reasons[] = 'quarterly-restore-evidence-not-passed';
        }
        foreach (array('completed_at', 'reviewed_at') as $field) {
            if (!self::utcTimestamp($evidence[$field] ?? null)) {
                $reasons[] = 'quarterly-restore-evidence-invalid-'.$field;
            }
        }
        foreach (array('backup_evidence_uuid', 'reviewed_by', 'evidence_reference') as $field) {
            if (!self::nonEmpty($evidence[$field] ?? null)) {
                $reasons[] = 'quarterly-restore-evidence-missing-'.$field;
            }
        }
        $completed = self::timestamp($evidence['completed_at'] ?? null);
        if ($completed !== null && ($completed > $now || $completed < $now->modify('-92 days'))) {
            $reasons[] = 'quarterly-restore-evidence-stale-or-future';
        }
    }

    private static function validateCustomerExit($evidence, DateTimeImmutable $now, array &$reasons)
    {
        if (!is_array($evidence)) {
            $reasons[] = 'customer-exit-access-evidence-missing';
            return;
        }
        if (($evidence['status'] ?? null) !== 'passed') {
            $reasons[] = 'customer-exit-access-evidence-not-passed';
        }
        foreach (array('tested_at', 'reviewed_at') as $field) {
            if (!self::utcTimestamp($evidence[$field] ?? null)) {
                $reasons[] = 'customer-exit-access-evidence-invalid-'.$field;
            }
        }
        foreach (array('access_reference', 'reviewed_by') as $field) {
            if (!self::nonEmpty($evidence[$field] ?? null)) {
                $reasons[] = 'customer-exit-access-evidence-missing-'.$field;
            }
        }
        if (!preg_match('/^[a-f0-9]{64}$/', strtolower((string) ($evidence['export_sha256'] ?? '')))) {
            $reasons[] = 'customer-exit-access-evidence-invalid-export-hash';
        }
        $tested = self::timestamp($evidence['tested_at'] ?? null);
        if ($tested !== null && ($tested > $now || $tested < $now->modify('-365 days'))) {
            $reasons[] = 'customer-exit-access-evidence-stale-or-future';
        }
    }

    private static function utcTimestamp($value)
    {
        if (!is_string($value)) {
            return false;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i:s\\Z', $value);
        return $date && $date->format('Y-m-d\\TH:i:s\\Z') === $value;
    }

    private static function timestamp($value)
    {
        return self::utcTimestamp($value) ? DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i:s\\Z', $value) : null;
    }

    private static function nonEmpty($value)
    {
        return is_string($value) && trim($value) !== '';
    }

    private static function hasWeeklySchedule($value)
    {
        return is_string($value) && preg_match('/\bweekly\b/i', $value) === 1;
    }

    private static function hasDailySchedule($value)
    {
        return is_string($value) && preg_match('/\bdaily\b/i', $value) === 1;
    }

    private static function date($value)
    {
        $date = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new RuntimeException('Invalid required retention date');
        }

        return $date;
    }
}
