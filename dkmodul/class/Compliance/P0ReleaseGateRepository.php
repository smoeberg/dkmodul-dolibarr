<?php

require_once __DIR__.'/P0ReleaseGate.php';

final class DkP0ReleaseGateRepository
{
    private $db;

    public function __construct($db) { $this->db = $db; }

    public function latestCheck($entity, $deploymentId, $checkType)
    {
        $sql = 'SELECT check_uuid,status,reason_codes,evidence_sha256,checked_at,next_due_at FROM '
            .$this->db->prefix().'dk_compliance_check WHERE entity='.(int) $entity
            .' AND deployment_id='.$this->quote($deploymentId).' AND check_type='.$this->quote($checkType)
            .' ORDER BY checked_at DESC,rowid DESC LIMIT 1';
        $result = $this->db->query($sql);
        if (!$result) throw new RuntimeException('Unable to read latest P0 compliance check: '.$this->db->lasterror());
        $row = $this->db->fetch_object($result);
        if (!$row) return null;
        $data = get_object_vars($row);
        $reasons = json_decode($data['reason_codes'], true);
        $data['reason_codes'] = is_array($reasons) ? $reasons : array('invalid-check-reason-codes');
        return $data;
    }

    public function appendReport($entity, array $report)
    {
        $expectedHash = $report['report_sha256'] ?? '';
        $payload = $report;
        unset($payload['report_sha256']);
        if (!preg_match('/^[a-f0-9]{64}$/', (string) $expectedHash)
            || !hash_equals($expectedHash, hash('sha256', DkP0ReleaseGate::canonicalJson($payload)))) {
            throw new RuntimeException('P0 release report hash verification failed');
        }
        $json = DkP0ReleaseGate::canonicalJson($report);
        $uuid = self::uuidV4();
        $generatedAt = new DateTimeImmutable($report['generated_at']);
        $sql = 'INSERT INTO '.$this->db->prefix().'dk_p0_release_report'
            .' (entity,report_uuid,deployment_id,product_release,decision,report_sha256,report_json,generated_at,created_at) VALUES ('
            .(int) $entity.','.$this->quote($uuid).','.$this->quote($report['deployment_id']).','.$this->quote($report['release']).','
            .$this->quote($report['decision']).','.$this->quote($expectedHash).','.$this->quote($json).','
            .$this->quote($generatedAt->format('Y-m-d H:i:s')).',NOW())';
        if (!$this->db->query($sql)) throw new RuntimeException('Unable to append P0 release report: '.$this->db->lasterror());
        return $uuid;
    }

    private function quote($value) { return "'".$this->db->escape((string) $value)."'"; }

    private static function uuidV4()
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        $hex = bin2hex($data);
        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20);
    }
}
