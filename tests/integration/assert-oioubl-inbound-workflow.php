<?php

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);

require '/var/www/html/main.inc.php';
require '/var/www/html/custom/dkmodul/class/EInvoice/Inbound/InboundWorkflowService.php';

$service = new DkInboundWorkflowService($db);
$rows = $service->list(1);
if (!$rows) throw new RuntimeException('Inbound workflow did not expose the staged invoice');
$row = null;
foreach ($rows as $candidate) if ($candidate['state'] === 'posted') $row = $candidate;
if ($row === null) throw new RuntimeException('Inbound workflow did not expose the posted invoice');
if ($row['state'] !== 'posted' || $row['nextAction'] !== null || $row['draftRowId'] <= 0
    || $row['supplierValidationRowId'] <= 0 || $row['postingRowId'] <= 0 || $row['pieceNum'] <= 0) {
    throw new RuntimeException('Inbound workflow did not derive the completed state');
}
$single = $service->get(1, $row['inboundRowId']);
if ($single !== $row) throw new RuntimeException('Inbound workflow list and detail states differ');
$journals = $service->purchaseJournals(1);
if (!$journals || $journals[0]['rowid'] <= 0 || $journals[0]['code'] === '') {
    throw new RuntimeException('Inbound workflow did not expose an active purchase journal');
}

echo "Inbound user workflow exposes a controlled completed state\n";
