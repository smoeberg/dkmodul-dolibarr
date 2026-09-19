<?php

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);

require '/var/www/html/main.inc.php';
require '/var/www/html/custom/dkmodul/class/EInvoice/Inbound/InboundInvoiceStagingService.php';

$xml = file_get_contents('/tmp/DKVAT-1.xml');
$schematron = file_get_contents('/tmp/OIOUBL_Invoice_Schematron_Result.xml');
if ($xml === false || $schematron === false) throw new RuntimeException('Inbound OIOUBL fixtures are missing');

$service = new DkInboundInvoiceStagingService($db, '/var/www/documents/dkmodul/inbound');
$first = $service->stage(1, 'nemhandel', 'provider-inbound-1', 'GLN', '5790001968502', $xml, 1);
$duplicate = $service->stage(1, 'nemhandel', 'provider-inbound-1', 'GLN', '5790001968502', $xml, 1);
if ($first['rowid'] !== $duplicate['rowid']) throw new RuntimeException('Inbound redelivery is not idempotent');

$conflictRejected = false;
try {
    $service->stage(1, 'nemhandel', 'provider-inbound-1', 'GLN', '5790001968502', $xml."\n", 1);
} catch (RuntimeException $e) {
    $conflictRejected = str_contains($e->getMessage(), 'different bytes');
}
if (!$conflictRejected) throw new RuntimeException('Conflicting inbound redelivery was not rejected');

$validated = $service->validate(1, $first['rowid'], '/tmp/oioubl-schema/maindoc/UBL-Invoice-2.1.xsd', $schematron, 1);
$reused = $service->validate(1, $first['rowid'], '/tmp/oioubl-schema/maindoc/UBL-Invoice-2.1.xsd', $schematron, 1);
if ($validated['state'] !== 'validated' || !$reused['reused']) throw new RuntimeException('Inbound validation is not idempotent');
if (($validated['metadata']['invoiceId'] ?? '') !== 'DKVAT-1'
    || ($validated['metadata']['supplierEndpoint'] ?? '') !== 'DK12345678'
    || ($validated['metadata']['customerEndpoint'] ?? '') !== '5790001968502') {
    throw new RuntimeException('Unexpected inbound invoice identity');
}

echo 'Inbound OIOUBL staged and officially validated: '.$first['rowid']."\n";
