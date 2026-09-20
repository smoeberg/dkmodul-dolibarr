<?php

$source = '/tmp/OIOUBL_CreditNote_v2p1.xml';
$target = '/tmp/DKCR-1.xml';
$dom = new DOMDocument();
if (!$dom->load($source, LIBXML_NONET)) throw new RuntimeException('Official OIOUBL credit-note fixture is missing');
$xp = new DOMXPath($dom);
$xp->registerNamespace('doc', 'urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2');
$xp->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
$xp->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');

$set = static function (string $query, string $value) use ($xp): void {
    $node = $xp->query($query)->item(0);
    if (!$node) throw new RuntimeException('Official credit-note fixture is missing '.$query);
    $node->nodeValue = $value;
};

$set('/doc:CreditNote/cbc:ID', 'DKCR-1');
$set('/doc:CreditNote/cbc:UUID', 'b9f95e39-60f8-42d2-a4f4-fbb3410f94b4');
$set('/doc:CreditNote/cbc:IssueDate', '2026-09-20');
$set('/doc:CreditNote/cac:BillingReference/cac:InvoiceDocumentReference/cbc:ID', 'DKVAT-1');
$set('/doc:CreditNote/cac:AccountingSupplierParty/cac:Party/cbc:EndpointID', 'DK12345678');
$set('/doc:CreditNote/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cbc:CompanyID', 'DK12345678');
$set('/doc:CreditNote/cac:AccountingCustomerParty/cac:Party/cbc:EndpointID', '5790001968502');

$lines = $xp->query('/doc:CreditNote/cac:CreditNoteLine');
while ($lines->length > 1) $lines->item($lines->length - 1)->parentNode->removeChild($lines->item($lines->length - 1));
$set('/doc:CreditNote/cac:CreditNoteLine/cbc:CreditedQuantity', '1.00');
$set('/doc:CreditNote/cac:CreditNoteLine/cbc:LineExtensionAmount', '250.00');
$set('/doc:CreditNote/cac:CreditNoteLine/cac:TaxTotal/cbc:TaxAmount', '62.50');
$set('/doc:CreditNote/cac:CreditNoteLine/cac:TaxTotal/cac:TaxSubtotal/cbc:TaxableAmount', '250.00');
$set('/doc:CreditNote/cac:CreditNoteLine/cac:TaxTotal/cac:TaxSubtotal/cbc:TaxAmount', '62.50');
$set('/doc:CreditNote/cac:CreditNoteLine/cac:Price/cbc:PriceAmount', '250.00');
$set('/doc:CreditNote/cac:TaxTotal/cbc:TaxAmount', '62.50');
$set('/doc:CreditNote/cac:TaxTotal/cac:TaxSubtotal/cbc:TaxableAmount', '250.00');
$set('/doc:CreditNote/cac:TaxTotal/cac:TaxSubtotal/cbc:TaxAmount', '62.50');
$set('/doc:CreditNote/cac:LegalMonetaryTotal/cbc:LineExtensionAmount', '250.00');
$set('/doc:CreditNote/cac:LegalMonetaryTotal/cbc:TaxExclusiveAmount', '250.00');
$set('/doc:CreditNote/cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount', '312.50');
$set('/doc:CreditNote/cac:LegalMonetaryTotal/cbc:PayableAmount', '312.50');

if ($dom->save($target) === false) throw new RuntimeException('Unable to write controlled credit-note fixture');
echo "Prepared official OIOUBL credit-note fixture\n";
