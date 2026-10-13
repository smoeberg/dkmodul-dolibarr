<?php

final class DkBackupOperationalPolicy
{
    public static function validate(array $backup, DateTimeImmutable $now, $requiredRetainUntil)
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

        return array_values(array_unique($reasons));
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
