<?php

final class DkAccessPointCertificateOperationsPolicy
{
    public static function validate(array $evidence, DateTimeImmutable $now)
    {
        $reasons = array();
        foreach (array(
            'certificate_serial',
            'issuer',
            'renewal_contact',
            'revocation_contact',
            'last_rotation_at',
            'revocation_tested_at',
            'evidence_reference',
        ) as $field) {
            if (!isset($evidence[$field]) || !is_string($evidence[$field]) || trim($evidence[$field]) === '') {
                $reasons[] = 'access-point-certificate-operations-missing-'.$field;
            }
        }

        foreach (array('last_rotation_at', 'revocation_tested_at') as $field) {
            if (!isset($evidence[$field]) || !is_string($evidence[$field])) {
                continue;
            }
            $value = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $evidence[$field], new DateTimeZone('UTC'));
            if (!$value || $value->format('Y-m-d\TH:i:s\Z') !== $evidence[$field]) {
                $reasons[] = 'access-point-certificate-operations-invalid-'.$field;
            } elseif ($value > $now) {
                $reasons[] = 'access-point-certificate-operations-future-'.$field;
            }
        }

        if (isset($evidence['certificate_serial']) && !preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $evidence['certificate_serial'])) {
            $reasons[] = 'access-point-certificate-operations-invalid-certificate-serial';
        }

        return array(
            'compliant' => count($reasons) === 0,
            'reasons' => array_values(array_unique($reasons)),
        );
    }
}
