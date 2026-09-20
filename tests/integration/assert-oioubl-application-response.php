<?php

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);

require '/var/www/html/main.inc.php';
require '/var/www/html/custom/dkmodul/class/EInvoice/Transport/ApplicationResponseService.php';

$resql = $db->query("SELECT d.rowid FROM ".$db->prefix()."dk_einvoice_delivery d INNER JOIN ".$db->prefix()."dk_document_archive a ON a.rowid=d.document_archive_rowid AND a.entity=d.entity WHERE d.entity=1 AND a.source_type='oioubl_invoice' ORDER BY d.rowid LIMIT 1");
$delivery = $resql ? $db->fetch_object($resql) : false;
if (!$delivery) throw new RuntimeException('Outbound invoice delivery is missing');
$xml = (string) file_get_contents('/tmp/DKVAT-1-ApplicationResponse.xml');
$schematron = (string) file_get_contents('/tmp/OIOUBL_ApplicationResponse_Schematron_Result.xml');
$service = new DkApplicationResponseService($db, '/var/www/documents/dkmodul/archive');
$first = $service->receive(1, (int) $delivery->rowid, 'nemhandel', 'provider-apr-1', 'GLN', '5790001968502', $xml, '/tmp/oioubl-schema/maindoc/UBL-ApplicationResponse-2.1.xsd', $schematron, 1);
$again = $service->receive(1, (int) $delivery->rowid, 'nemhandel', 'provider-apr-1', 'GLN', '5790001968502', $xml, '/tmp/oioubl-schema/maindoc/UBL-ApplicationResponse-2.1.xsd', $schematron, 1);
if ($first['state'] !== 'accepted' || $first['responseCode'] !== 'BusinessAccept' || !$again['reused'] || $again['rowid'] !== $first['rowid']) {
    throw new RuntimeException('OIOUBL application response was not accepted idempotently');
}

$mismatchRejected = false;
try {
    $service->receive(1, (int) $delivery->rowid, 'nemhandel', 'provider-apr-1', 'GLN', '5790001968502', str_replace('Invoice accepted', 'Changed response', $xml), '/tmp/oioubl-schema/maindoc/UBL-ApplicationResponse-2.1.xsd', $schematron, 1);
} catch (RuntimeException $e) {
    $mismatchRejected = str_contains($e->getMessage(), 'different bytes');
}
if (!$mismatchRejected) throw new RuntimeException('Application response message identity accepted different bytes');

$bindingRejected = false;
try {
    $service->receive(1, (int) $delivery->rowid, 'nemhandel', 'provider-apr-wrong-reference', 'GLN', '5790001968502', str_replace('<cbc:ID>DKVAT-1</cbc:ID>', '<cbc:ID>OTHER-INVOICE</cbc:ID>', $xml), '/tmp/oioubl-schema/maindoc/UBL-ApplicationResponse-2.1.xsd', $schematron, 1);
} catch (RuntimeException $e) {
    $bindingRejected = str_contains($e->getMessage(), 'does not reference');
}
if (!$bindingRejected) throw new RuntimeException('Application response accepted a different document reference');

$row = $db->query('SELECT response_id,response_code,referenced_document_id,evidence_hash,content_hash FROM '.$db->prefix().'dk_einvoice_application_response WHERE rowid='.(int) $first['rowid']);
$stored = $row ? $db->fetch_object($row) : false;
if (!$stored || $stored->response_id !== 'APR-DKVAT-1' || $stored->response_code !== 'BusinessAccept'
    || $stored->referenced_document_id !== 'DKVAT-1' || strlen((string) $stored->evidence_hash) !== 64 || strlen((string) $stored->content_hash) !== 64) {
    throw new RuntimeException('Stored application-response evidence is incomplete');
}

echo 'Controlled OIOUBL application response accepted: '.$first['rowid']."\n";
