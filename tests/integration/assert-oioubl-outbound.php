<?php

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);

require '/var/www/html/main.inc.php';
require '/var/www/html/custom/dkmodul/class/EInvoice/DolibarrOutboundInvoiceProvider.php';
require '/var/www/html/custom/dkmodul/class/EInvoice/OioUblInvoiceGenerator.php';
require '/var/www/html/custom/dkmodul/class/EInvoice/OioUblValidator.php';
require '/var/www/html/custom/dkmodul/class/Documents/DocumentArchiveService.php';

$resql = $db->query("SELECT rowid FROM ".$db->prefix()."facture WHERE entity=1 AND ref='DKVAT-1'");
$source = $resql ? $db->fetch_object($resql) : false;
if (!$source) throw new RuntimeException('OIOUBL source invoice is missing');

$provider = new DkDolibarrOutboundInvoiceProvider($db, 1, array(
    'endpointId' => 'DK12345678', 'endpointScheme' => 'DK:CVR',
    'registrationName' => 'Dolibarr DK Test ApS', 'companyId' => 'DK12345678',
    'street' => 'Testvej 1', 'city' => 'Aarhus C', 'postalCode' => '8000', 'countryCode' => 'DK',
    'contactName' => 'Integration Test', 'email' => 'test@example.invalid',
    'currencyCode' => 'DKK', 'paymentMeansCode' => '42', 'bankAccount' => 'DK5000400440116243',
));
$invoice = $provider->getInvoice((int) $source->rowid, '5790001968502', 'GLN');
$xml = (new DkOioUblInvoiceGenerator())->generate($invoice);

$validator = new DkOioUblValidator();
$validator->validateXsd($xml, '/tmp/oioubl-schema/maindoc/UBL-Invoice-2.1.xsd');
$validator->validateSchematron($xml, '/tmp/OIOUBL_Invoice_Schematron.xsl');

$resql = $db->query("SELECT rowid FROM ".$db->prefix()."accounting_bookkeeping WHERE entity=1 AND piece_num=990003 ORDER BY rowid LIMIT 1");
$bookkeeping = $resql ? $db->fetch_object($resql) : false;
if (!$bookkeeping) throw new RuntimeException('OIOUBL bookkeeping link is missing');

$path = '/tmp/DKVAT-1.xml';
file_put_contents($path, $xml);
$archived = (new DkDocumentArchiveService($db, '/var/www/documents/dkmodul/archive'))->archive(
    1, (int) $bookkeeping->rowid, 'oioubl_invoice', (int) $source->rowid,
    $path, 'DKVAT-1.xml', '2026-12-31', 1, 'application/xml'
);

$dom = new DOMDocument();
$dom->loadXML($xml);
$xpath = new DOMXPath($dom);
$xpath->registerNamespace('i', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
$xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
if ($xpath->evaluate('string(/i:Invoice/cbc:ID)') !== 'DKVAT-1' || $archived['retainUntil'] !== '2031-12-31') {
    throw new RuntimeException('Unexpected OIOUBL output or retention evidence');
}

echo 'Officially validated OIOUBL invoice archived: '.$archived['rowid']."\n";
