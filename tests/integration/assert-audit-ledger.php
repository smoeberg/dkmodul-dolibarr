<?php

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);

require '/var/www/html/main.inc.php';
require '/var/www/html/custom/dkmodul/class/Audit/AuditLedger.php';

$resql = $db->query(
    'SELECT COUNT(*) AS nb FROM '.$db->prefix().'dk_audit_event WHERE entity=1'
);
$row = $db->fetch_object($resql);
if (!$row || (int) $row->nb < 2) {
    throw new RuntimeException('Audit integration fixture contains too few events');
}

$ledger = new DkAuditLedger($db);
if (!$ledger->verifyChain(1)) {
    throw new RuntimeException('Audit hash chain verification failed');
}

echo 'Audit ledger is append-only and its hash chain verifies for '.((int) $row->nb)." events\n";
