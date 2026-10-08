<?php

require_once __DIR__.'/../../dkmodul/class/EInvoice/Inbound/InboundOioUblEnvelope.php';

$xml = '<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
 xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
 xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
  <cac:AccountingSupplierParty>
    <cac:Party>
      <cbc:EndpointID schemeID="GLN">5798000000000</cbc:EndpointID>
    </cac:Party>
  </cac:AccountingSupplierParty>
</Invoice>';

$metadata = DkInboundOioUblEnvelope::inspect($xml);
if ($metadata['documentType'] !== 'Invoice'
    || $metadata['senderScheme'] !== 'GLN'
    || $metadata['senderId'] !== '5798000000000') {
    throw new RuntimeException('OIOUBL inbound envelope parsing failed');
}

$failed = false;
try {
    DkInboundOioUblEnvelope::inspect('<Invoice/>');
} catch (Throwable $e) {
    $failed = strpos($e->getMessage(), 'EndpointID') !== false;
}
if (!$failed) throw new RuntimeException('Inbound envelope without supplier endpoint was accepted');

$failed = false;
try {
    DkInboundOioUblEnvelope::inspect('<NotAnInvoice/>');
} catch (Throwable $e) {
    $failed = strpos($e->getMessage(), 'Invoice or CreditNote') !== false;
}
if (!$failed) throw new RuntimeException('Non-OIOUBL inbound document was accepted');

echo "Inexchange inbound envelope contract: PASS\n";
