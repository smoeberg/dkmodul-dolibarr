<?php

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);

require '/var/www/html/main.inc.php';
require '/var/www/html/custom/dkmodul/class/EInvoice/DolibarrOutboundInvoiceProvider.php';
require '/var/www/html/custom/dkmodul/class/EInvoice/OioUblInvoiceGenerator.php';
require '/var/www/html/custom/dkmodul/class/EInvoice/OioUblValidator.php';
require '/var/www/html/custom/dkmodul/class/Documents/DocumentArchiveService.php';
require '/var/www/html/custom/dkmodul/class/EInvoice/Transport/OutboundDeliveryService.php';

final class DkCreditNoteTransportAdapter implements DkTransportAdapterInterface
{
    public int $calls = 0;
    public function send(DkOutboundEnvelope $envelope): DkTransportReceipt
    {
        $this->calls++;
        if (!str_contains($envelope->payload, 'CreditNote')) throw new RuntimeException('Transport did not receive credit-note XML');
        return new DkTransportReceipt('accepted', 'credit-'.$envelope->deliveryUuid, 'DELIVERED', '2026-09-20 20:00:00', array('type' => 'CreditNote'));
    }
}

$resql = $db->query("SELECT rowid FROM ".$db->prefix()."facture WHERE entity=1 AND ref='DKCR-OUT-1' AND type=2 AND fk_statut=1");
$source = $resql ? $db->fetch_object($resql) : false;
if (!$source) throw new RuntimeException('Validated outbound credit-note source is missing');
$provider = new DkDolibarrOutboundInvoiceProvider($db, 1, array(
    'endpointId' => 'DK12345678', 'endpointScheme' => 'DK:CVR',
    'registrationName' => 'Dolibarr DK Test ApS', 'companyId' => 'DK12345678',
    'street' => 'Testvej', 'buildingNumber' => '1', 'city' => 'Aarhus C', 'postalCode' => '8000', 'countryCode' => 'DK',
    'contactName' => 'Integration Test', 'email' => 'test@example.invalid',
    'currencyCode' => 'DKK', 'paymentMeansCode' => '42', 'bankAccount' => '440116243', 'bankRegistrationNumber' => '0040',
));
$credit = $provider->getInvoice((int) $source->rowid, '5790001968502', 'GLN', 'PO-990003');
if ($credit->documentType !== 'CreditNote' || $credit->creditedInvoiceId !== 'DKVAT-1' || $credit->taxInclusiveAmount !== '312.50000000') {
    throw new RuntimeException('Canonical outbound credit-note identity is invalid');
}
$xml = (new DkOioUblInvoiceGenerator())->generate($credit);
$validator = new DkOioUblValidator();
$validator->validateXsd($xml, '/tmp/oioubl-schema/maindoc/UBL-CreditNote-2.1.xsd');
$xmlPath = '/tmp/DKCR-OUT-1.xml';
$reportPath = '/tmp/OIOUBL_Outbound_CreditNote_Schematron_Result.xml';
file_put_contents($xmlPath, $xml);
if (!is_file($reportPath)) {
    echo "Outbound OIOUBL credit-note XSD candidate ready for Schematron\n";
    exit(0);
}
$validator->validateSchematronResult((string) file_get_contents($reportPath));

$dom = new DOMDocument();
$dom->loadXML($xml);
$xp = new DOMXPath($dom);
$xp->registerNamespace('c', 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2');
$xp->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
$xp->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
if ($xp->evaluate('string(/c:CreditNote/cbc:ID)') !== 'DKCR-OUT-1'
    || $xp->evaluate('string(/c:CreditNote/cac:BillingReference/cac:InvoiceDocumentReference/cbc:ID)') !== 'DKVAT-1'
    || $xp->evaluate('string(/c:CreditNote/cac:CreditNoteLine/cbc:LineExtensionAmount)') !== '250.00') {
    throw new RuntimeException('Unexpected outbound OIOUBL credit-note structure');
}

$resql = $db->query("SELECT rowid FROM ".$db->prefix()."accounting_bookkeeping WHERE entity=1 AND piece_num=990008 ORDER BY rowid LIMIT 1");
$bookkeeping = $resql ? $db->fetch_object($resql) : false;
if (!$bookkeeping) throw new RuntimeException('Outbound credit-note bookkeeping link is missing');
$archive = new DkDocumentArchiveService($db, '/var/www/documents/dkmodul/archive');
$archived = $archive->archive(1, (int) $bookkeeping->rowid, 'oioubl_credit_note', (int) $source->rowid, $xmlPath, 'DKCR-OUT-1.xml', '2026-12-31', 1, 'application/xml');
if (!$archive->verify(1, $archived['rowid'])) throw new RuntimeException('Archived credit-note bytes failed verification');

$delivery = new DkOutboundDeliveryService($db, '/var/www/documents/dkmodul/archive');
$queued = $delivery->queue(1, $archived['rowid'], 'nemhandel', 'GLN', '5790001968502', 1);
$queuedAgain = $delivery->queue(1, $archived['rowid'], 'nemhandel', 'GLN', '5790001968502', 1);
$adapter = new DkCreditNoteTransportAdapter();
$sent = $delivery->dispatch(1, $queued['rowid'], $adapter, 1);
$sentAgain = $delivery->dispatch(1, $queued['rowid'], $adapter, 1);
if ($queuedAgain['rowid'] !== $queued['rowid'] || $sent['state'] !== 'accepted' || !$sentAgain['reused'] || $adapter->calls !== 1) {
    throw new RuntimeException('Outbound credit-note delivery is not idempotent');
}

echo 'Officially validated, archived and delivered outbound OIOUBL credit note: '.$archived['rowid']."\n";
