<?php

/**
 * Append-only application audit ledger.
 *
 * The hash chain is evidence of application-level tampering. It is not a
 * substitute for infrastructure/database access controls.
 */
class DkAuditLedger
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function append($entity, $eventType, $objectType, $objectId, $actorId, array $payload = array(), array $metadata = array())
    {
        $entity = (int) $entity;
        $previousHash = $this->getLastHash($entity);
        $payloadJson = $this->canonicalJson($payload);
        $metadataJson = $this->canonicalJson($metadata);
        $payloadHash = hash('sha256', $payloadJson);
        $createdAt = gmdate('Y-m-d H:i:s');
        $eventUuid = $this->uuidV4();

        $eventHash = hash('sha256', implode('|', array(
            $previousHash,
            $eventUuid,
            $eventType,
            $objectType,
            (string) ((int) $objectId),
            (string) ((int) $actorId),
            $createdAt,
            $payloadHash,
            hash('sha256', $metadataJson),
        )));

        $sql = 'INSERT INTO '.$this->db->prefix().'dk_audit_event';
        $sql .= ' (entity,event_uuid,event_type,object_type,object_id,actor_id,created_at,previous_hash,payload_hash,event_hash,payload_json,metadata_json)';
        $sql .= " VALUES (".$entity;
        $sql .= ",'".$this->db->escape($eventUuid)."'";
        $sql .= ",'".$this->db->escape($eventType)."'";
        $sql .= ",'".$this->db->escape($objectType)."'";
        $sql .= ",".((int) $objectId);
        $sql .= ",".((int) $actorId);
        $sql .= ",'".$this->db->escape($createdAt)."'";
        $sql .= ",'".$this->db->escape($previousHash)."'";
        $sql .= ",'".$this->db->escape($payloadHash)."'";
        $sql .= ",'".$this->db->escape($eventHash)."'";
        $sql .= ",'".$this->db->escape($payloadJson)."'";
        $sql .= ",'".$this->db->escape($metadataJson)."')";

        if (!$this->db->query($sql)) {
            throw new RuntimeException('Unable to append DK audit event: '.$this->db->lasterror());
        }

        return $eventHash;
    }

    public function verifyChain($entity)
    {
        $sql = 'SELECT event_uuid,event_type,object_type,object_id,actor_id,created_at,previous_hash,payload_hash,event_hash,metadata_json';
        $sql .= ' FROM '.$this->db->prefix().'dk_audit_event';
        $sql .= ' WHERE entity = '.((int) $entity).' ORDER BY rowid ASC';

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RuntimeException('Unable to read DK audit chain: '.$this->db->lasterror());
        }

        $previous = str_repeat('0', 64);

        while ($row = $this->db->fetch_object($resql)) {
            if ($row->previous_hash !== $previous) {
                return false;
            }

            $expected = hash('sha256', implode('|', array(
                $row->previous_hash,
                $row->event_uuid,
                $row->event_type,
                $row->object_type,
                (string) ((int) $row->object_id),
                (string) ((int) $row->actor_id),
                $row->created_at,
                $row->payload_hash,
                hash('sha256', $row->metadata_json),
            )));

            if (!hash_equals($expected, $row->event_hash)) {
                return false;
            }

            $previous = $row->event_hash;
        }

        return true;
    }

    private function getLastHash($entity)
    {
        $sql = 'SELECT event_hash FROM '.$this->db->prefix().'dk_audit_event';
        $sql .= ' WHERE entity = '.((int) $entity).' ORDER BY rowid DESC LIMIT 1';

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RuntimeException('Unable to read last DK audit hash: '.$this->db->lasterror());
        }

        $row = $this->db->fetch_object($resql);
        return $row ? $row->event_hash : str_repeat('0', 64);
    }

    private function canonicalJson(array $value)
    {
        $this->recursiveKsort($value);
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function recursiveKsort(array &$value)
    {
        ksort($value);
        foreach ($value as &$item) {
            if (is_array($item)) {
                $this->recursiveKsort($item);
            }
        }
    }

    private function uuidV4()
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
