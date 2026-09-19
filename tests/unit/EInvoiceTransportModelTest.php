<?php

require_once __DIR__.'/../../dkmodul/class/EInvoice/Transport/TransportAdapterInterface.php';

$payload = '<Invoice>test</Invoice>';
$envelope = new DkOutboundEnvelope(
    'bb85eb72-3371-4d85-a9ee-981d957c9501',
    hash('sha256', 'delivery-key'),
    'nemhandel', 'GLN', '5790001968502', 'application/xml',
    hash('sha256', $payload), $payload
);
assert($envelope->payload === $payload);

$receipt = new DkTransportReceipt('accepted', 'provider-42', 'DELIVERED', '2026-09-19 16:00:00', array('status' => 'ok'));
assert($receipt->status === 'accepted');

$rejected = false;
try {
    new DkOutboundEnvelope('id', 'key', 'nemhandel', 'GLN', '1', 'application/xml', str_repeat('0', 64), $payload);
} catch (InvalidArgumentException $e) {
    $rejected = true;
}
assert($rejected === true);

$rejected = false;
try {
    new DkTransportReceipt('accepted', '', 'DELIVERED', '2026-09-19 16:00:00');
} catch (InvalidArgumentException $e) {
    $rejected = true;
}
assert($rejected === true);

echo "E-invoice transport model tests passed\n";
