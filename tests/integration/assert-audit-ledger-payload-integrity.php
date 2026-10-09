<?php

require_once __DIR__.'/../../dkmodul/class/Audit/AuditLedger.php';

final class AuditVerifyResult
{
    private $rows;
    private $index = 0;
    public function __construct(array $rows) { $this->rows = $rows; }
    public function next()
    {
        if (!isset($this->rows[$this->index])) return null;
        return (object) $this->rows[$this->index++];
    }
}

final class AuditVerifyDb
{
    public $rows = array();
    public function prefix() { return 'llx_'; }
    public function query($sql)
    {
        if (strpos($sql, 'SELECT event_uuid,event_type,object_type') !== false) {
            return new AuditVerifyResult($this->rows);
        }
        throw new RuntimeException('Unexpected SQL: '.$sql);
    }
    public function fetch_object($result) { return $result->next(); }
    public function lasterror() { return ''; }
}

function auditVerifyRow($payloadJson)
{
    $payloadHash = hash('sha256', $payloadJson);
    $metadataJson = '{}';
    $row = array(
        'event_uuid' => '00000000-0000-4000-8000-000000000001',
        'event_type' => 'test.event',
        'object_type' => 'test',
        'object_id' => 1,
        'actor_id' => 7,
        'created_at' => '2026-10-09 12:00:00',
        'previous_hash' => str_repeat('0', 64),
        'payload_hash' => $payloadHash,
        'payload_json' => $payloadJson,
        'metadata_json' => $metadataJson,
    );
    $row['event_hash'] = hash('sha256', implode('|', array(
        $row['previous_hash'],
        $row['event_uuid'],
        $row['event_type'],
        $row['object_type'],
        '1',
        '7',
        $row['created_at'],
        $payloadHash,
        hash('sha256', $metadataJson),
    )));
    return $row;
}

$db = new AuditVerifyDb();
$ledger = new DkAuditLedger($db);
$db->rows = array(auditVerifyRow('{"amount":"10.00"}'));
if (!$ledger->verifyChain(1)) {
    throw new RuntimeException('Valid audit payload was rejected');
}

$db->rows = array(auditVerifyRow('{"amount":"10.00"}'));
$db->rows[0]['payload_json'] = '{"amount":"999.00"}';
if ($ledger->verifyChain(1)) {
    throw new RuntimeException('Tampered audit payload was accepted');
}

echo "Audit ledger payload hash verification: PASS\n";
