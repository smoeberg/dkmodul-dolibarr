<?php

require_once __DIR__.'/../../dkmodul/class/EInvoice/OioUblInvoiceGenerator.php';

$invoice = new DkCanonicalInvoice(array(
    'sourceInvoiceId' => 42,
    'invoiceId' => 'INV-42',
    'uuid' => '4f1dcd0a-a945-5a77-9ca5-2b87bce813d0',
    'issueDate' => '2026-09-19',
    'dueDate' => '2026-10-19',
    'currencyCode' => 'DKK',
    'orderReference' => 'PO-42',
    'supplier' => array('endpointId' => 'DK12345678', 'endpointScheme' => 'DK:CVR', 'registrationName' => 'Supplier ApS', 'companyId' => 'DK12345678', 'street' => 'Testvej', 'buildingNumber' => '1', 'city' => 'Aarhus C', 'postalCode' => '8000', 'countryCode' => 'DK'),
    'customer' => array('endpointId' => '5790000000000', 'endpointScheme' => 'GLN', 'registrationName' => 'Customer A/S', 'companyId' => 'DK87654321', 'street' => 'Kundevej', 'buildingNumber' => '2', 'city' => 'København', 'postalCode' => '2100', 'countryCode' => 'DK'),
    'lines' => array(array('id' => '1', 'description' => 'Consulting', 'quantity' => '1', 'unitPrice' => '250', 'lineExtensionAmount' => '250', 'vatPercentage' => '25', 'taxAmount' => '62.5')),
    'taxExclusiveAmount' => '250', 'taxAmount' => '62.5', 'taxInclusiveAmount' => '312.5', 'payableAmount' => '312.5',
    'paymentMeansCode' => '42', 'paymentId' => 'INV-42', 'bankAccount' => '440116243', 'bankRegistrationNumber' => '0040',
));

$generator = new DkOioUblInvoiceGenerator();
$xml = $generator->generate($invoice);
$dom = new DOMDocument();
assert($dom->loadXML($xml) === true);
$xpath = new DOMXPath($dom);
$xpath->registerNamespace('i', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
$xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
$xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
assert($xpath->evaluate('string(/i:Invoice/cbc:CustomizationID)') === 'OIOUBL-2.02');
assert($xpath->evaluate('string(/i:Invoice/cac:LegalMonetaryTotal/cbc:PayableAmount)') === '312.50');
assert($xpath->evaluate('string(/i:Invoice/cac:InvoiceLine/cac:TaxTotal/cbc:TaxAmount)') === '62.50');
assert($generator->generate($invoice) === $xml);

$credit = new DkCanonicalInvoice(array(
    'sourceInvoiceId' => 43, 'documentType' => 'CreditNote', 'creditedInvoiceId' => 'INV-42',
    'invoiceId' => 'CR-43', 'uuid' => '31e60c75-fc57-5f7b-9320-581517ce6897',
    'issueDate' => '2026-09-20', 'dueDate' => '2026-09-20', 'currencyCode' => 'DKK', 'orderReference' => 'PO-42',
    'supplier' => $invoice->supplier, 'customer' => $invoice->customer, 'lines' => $invoice->lines,
    'taxExclusiveAmount' => '250', 'taxAmount' => '62.5', 'taxInclusiveAmount' => '312.5', 'payableAmount' => '312.5',
    'paymentMeansCode' => '42', 'paymentId' => 'CR-43', 'bankAccount' => '440116243', 'bankRegistrationNumber' => '0040',
));
$creditXml = $generator->generate($credit);
$creditDom = new DOMDocument();
assert($creditDom->loadXML($creditXml) === true);
$creditXp = new DOMXPath($creditDom);
$creditXp->registerNamespace('c', 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2');
$creditXp->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
$creditXp->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
assert($creditXp->evaluate('string(/c:CreditNote/cac:OrderReference/cbc:ID)') === 'PO-42');
assert($creditXp->evaluate('string(/c:CreditNote/cac:BillingReference/cac:InvoiceDocumentReference/cbc:ID)') === 'INV-42');
assert($creditXp->evaluate('string(/c:CreditNote/cac:CreditNoteLine/cbc:CreditedQuantity)') === '1.00');
assert($generator->generate($credit) === $creditXml);

$rejected = false;
try {
    new DkCanonicalInvoice(array(
        'sourceInvoiceId' => 1, 'invoiceId' => 'BAD', 'uuid' => $invoice->uuid,
        'issueDate' => '2026-01-01', 'dueDate' => '2026-01-01', 'currencyCode' => 'DKK', 'orderReference' => 'PO-BAD',
        'supplier' => $invoice->supplier, 'customer' => $invoice->customer,
        'lines' => $invoice->lines, 'taxExclusiveAmount' => '249', 'taxAmount' => '62.5',
        'taxInclusiveAmount' => '311.5', 'paymentMeansCode' => '42', 'paymentId' => 'BAD', 'bankAccount' => '1', 'bankRegistrationNumber' => '0040',
    ));
} catch (InvalidArgumentException $e) {
    $rejected = true;
}
assert($rejected === true);

echo "OIOUBL invoice generator tests passed\n";
