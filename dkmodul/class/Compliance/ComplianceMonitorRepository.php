<?php

final class DkComplianceMonitorRepository
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function appendCheck($entity, $deploymentId, $checkType, $status, array $reasonCodes, $evidenceSha256, DateTimeImmutable $checkedAt, DateTimeImmutable $nextDueAt)
    {
        $uuid = self::uuidV4();
        $sql = 'INSERT INTO '.$this->db->prefix().'dk_compliance_check'
            .' (entity,check_uuid,deployment_id,check_type,status,reason_codes,evidence_sha256,checked_at,next_due_at,created_at) VALUES ('
            .(int) $entity.','.$this->quote($uuid).','.$this->quote($deploymentId).','.$this->quote($checkType).','.$this->quote($status).','
            .$this->quote(json_encode(array_values($reasonCodes), JSON_UNESCAPED_SLASHES)).','.$this->quote($evidenceSha256).','
            .$this->quote($checkedAt->format('Y-m-d H:i:s')).','.$this->quote($nextDueAt->format('Y-m-d H:i:s')).',NOW())';
        $this->execute($sql, 'append compliance check');

        return $uuid;
    }

    public function latestAlertEventType($entity, $deploymentId, $fingerprint)
    {
        $sql = 'SELECT event_type FROM '.$this->db->prefix().'dk_compliance_alert WHERE entity='.(int) $entity
            .' AND deployment_id='.$this->quote($deploymentId).' AND fingerprint='.$this->quote($fingerprint)
            .' ORDER BY rowid DESC LIMIT 1';
        $result = $this->db->query($sql);
        if (!$result) {
            throw new RuntimeException('Unable to read DK compliance alert state: '.$this->db->lasterror());
        }
        $object = $this->db->fetch_object($result);

        return $object ? $object->event_type : null;
    }

    public function appendAlert($entity, $deploymentId, $checkType, array $event, $checkUuid)
    {
        $uuid = self::uuidV4();
        $sql = 'INSERT INTO '.$this->db->prefix().'dk_compliance_alert'
            .' (entity,alert_uuid,deployment_id,check_type,fingerprint,event_type,severity,reason_codes,check_uuid,created_at) VALUES ('
            .(int) $entity.','.$this->quote($uuid).','.$this->quote($deploymentId).','.$this->quote($checkType).','
            .$this->quote($event['fingerprint']).','.$this->quote($event['event_type']).','.$this->quote($event['severity']).','
            .$this->quote(json_encode($event['reason_codes'], JSON_UNESCAPED_SLASHES)).','.$this->quote($checkUuid).',NOW())';
        $this->execute($sql, 'append compliance alert');

        return $uuid;
    }

    public function appendDelivery($entity, $alertUuid, array $delivery, DateTimeImmutable $attemptedAt)
    {
        $uuid = self::uuidV4();
        $sql = 'INSERT INTO '.$this->db->prefix().'dk_compliance_alert_delivery'
            .' (entity,delivery_uuid,alert_uuid,channel,status,external_reference,attempted_at,created_at) VALUES ('
            .(int) $entity.','.$this->quote($uuid).','.$this->quote($alertUuid).','.$this->quote($delivery['channel']).','
            .$this->quote($delivery['status']).','.$this->quote($delivery['external_reference'] ?? '').','
            .$this->quote($attemptedAt->format('Y-m-d H:i:s')).',NOW())';
        $this->execute($sql, 'append compliance alert delivery');

        return $uuid;
    }

    private function quote($value)
    {
        return "'".$this->db->escape((string) $value)."'";
    }

    private function execute($sql, $operation)
    {
        if (!$this->db->query($sql)) {
            throw new RuntimeException('Unable to '.$operation.': '.$this->db->lasterror());
        }
    }

    private static function uuidV4()
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        $hex = bin2hex($data);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20);
    }
}
