<?php

require_once __DIR__.'/../../dkmodul/class/Audit/AuditLedger.php';

final class FakeAuditDb
{
    public $insertAttempts = 0;
    public $lastError = '';

    public function prefix() { return 'llx_'; }
    public function escape($value) { return addslashes((string) $value); }

    public function query($sql)
    {
        if (strpos($sql, 'SELECT event_hash') !== false) {
            return new FakeAuditResult();
        }

        if (strpos($sql, 'INSERT INTO llx_dk_audit_event') !== false) {
            $this->insertAttempts++;
            if ($this->insertAttempts === 1) {
                $this->lastError = "Duplicate entry 'previous' for key 'uk_dk_audit_previous_hash'";
                return false;
            }
            $this->lastError = '';
            return true;
        }

        throw new RuntimeException('Unexpected SQL in audit ledger test');
    }

    public function fetch_object($result)
    {
        return null;
    }

    public function lasterror() { return $this->lastError; }
}

final class FakeAuditResult {}

$db = new FakeAuditDb();
$ledger = new DkAuditLedger($db);
$hash = $ledger->append(1, 'test.concurrent', 'test', 1, 42);

if (!is_string($hash) || strlen($hash) !== 64 || $db->insertAttempts !== 2) {
    throw new RuntimeException('Audit ledger did not retry a concurrent previous-hash conflict');
}

echo "Audit ledger concurrent append retry: PASS\n";
