<?php

require_once __DIR__.'/../../dkmodul/class/Audit/AuditLedger.php';

final class FakeAuditDb
{
    public $insertAttempts = 0;
    public $lastError = '';
    public $events = array();
    private $interleaved = false;
    private $interleavedLedger;

    public function prefix() { return 'llx_'; }
    public function escape($value) { return addslashes((string) $value); }

    public function setInterleavedLedger(DkAuditLedger $ledger)
    {
        $this->interleavedLedger = $ledger;
    }

    public function query($sql)
    {
        if (strpos($sql, 'SELECT event_hash') !== false) {
            return new FakeAuditResult($this->events);
        }

        if (strpos($sql, 'INSERT INTO llx_dk_audit_event') !== false) {
            $this->insertAttempts++;

            if ($this->insertAttempts === 1 && !$this->interleaved) {
                $this->interleaved = true;
                $this->interleavedLedger->append(1, 'test.concurrent.inner', 'test', 2, 43);
                $this->lastError = "Duplicate entry 'previous' for key 'uk_dk_audit_previous_hash'";
                return false;
            }

            $values = array();
            preg_match_all("/'([^']*)'/", $sql, $matches);
            $values = $matches[1];

            $this->events[] = array(
                'event_uuid' => $values[0],
                'event_type' => $values[1],
                'object_type' => $values[2],
                'object_id' => 1,
                'actor_id' => 42,
                'created_at' => $values[3],
                'previous_hash' => $values[4],
                'payload_hash' => $values[5],
                'event_hash' => $values[6],
                'metadata_json' => $values[8],
            );
            $this->lastError = '';
            return true;
        }

        throw new RuntimeException('Unexpected SQL in audit ledger test');
    }

    public function fetch_object($result)
    {
        return $result->next();
    }

    public function lasterror() { return $this->lastError; }
}

final class FakeAuditResult
{
    private $events;
    private $index = 0;

    public function __construct(array $events)
    {
        $this->events = $events;
    }

    public function next()
    {
        if (!isset($this->events[$this->index])) {
            return null;
        }

        return (object) $this->events[$this->index++];
    }
}

$db = new FakeAuditDb();
$outerLedger = new DkAuditLedger($db);
$innerLedger = new DkAuditLedger($db);
$db->setInterleavedLedger($innerLedger);

$hash = $outerLedger->append(1, 'test.concurrent.outer', 'test', 1, 42);

if (!is_string($hash) || strlen($hash) !== 64) {
    throw new RuntimeException('Audit ledger did not return an event hash after concurrent retry');
}

if ($db->insertAttempts !== 3 || count($db->events) !== 2) {
    throw new RuntimeException('Audit ledger did not survive the simulated concurrent append');
}

if ($db->events[1]['previous_hash'] !== $db->events[0]['event_hash']) {
    throw new RuntimeException('Audit ledger retry did not chain to the interleaved event');
}

echo "Audit ledger concurrent append interleaving: PASS\n";
