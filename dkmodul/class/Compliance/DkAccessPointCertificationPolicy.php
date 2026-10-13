<?php

final class DkAccessPointCertificationPolicy
{
    public static function validate(array $manifestAccessPoint, $evidence, DateTimeImmutable $now)
    {
        $reasons = array();
        if (!is_array($evidence)) return array('compliant'=>false,'reasons'=>array('access-point-certification-evidence-missing'));
        foreach (array('certification_id','certificate_sha256','status','valid_from','valid_until','evidence_reference','reviewed_by','reviewed_at') as $field) {
            if (!isset($evidence[$field]) || !is_string($evidence[$field]) || trim($evidence[$field]) === '') $reasons[]='access-point-certification-evidence-missing-'.$field;
        }
        if (isset($evidence['status']) && $evidence['status'] !== 'certified') $reasons[]='access-point-certification-evidence-not-certified';
        if (isset($evidence['certificate_sha256']) && !preg_match('/^[a-f0-9]{64}$/', strtolower($evidence['certificate_sha256']))) $reasons[]='access-point-certification-evidence-invalid-certificate-hash';
        if (isset($evidence['valid_from'],$evidence['valid_until'])) {
            $from=DateTimeImmutable::createFromFormat('!Y-m-d',$evidence['valid_from']); $until=DateTimeImmutable::createFromFormat('!Y-m-d',$evidence['valid_until']);
            if (!$from || !$until || $from->format('Y-m-d')!==$evidence['valid_from'] || $until->format('Y-m-d')!==$evidence['valid_until']) $reasons[]='access-point-certification-evidence-invalid-validity';
            elseif ($from>$now || $until<$now || $until<$from) $reasons[]='access-point-certification-evidence-expired-or-future';
        }
        foreach (array('certification_id','certificate_sha256','valid_from','valid_until') as $field) {
            if (isset($manifestAccessPoint[$field],$evidence[$field]) && strtolower((string)$manifestAccessPoint[$field])!==strtolower((string)$evidence[$field])) $reasons[]='access-point-'.($field === 'certification_id' ? 'certification-id' : $field).'-mismatch';
        }
        return array('compliant'=>count($reasons)===0,'reasons'=>array_values(array_unique($reasons)));
    }
}
