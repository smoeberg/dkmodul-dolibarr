<?php

require_once __DIR__.'/../../dkmodul/class/EInvoice/Transport/AccessPointTransportAdapter.php';
require_once __DIR__.'/../../dkmodul/class/AccessPoint/DkAccessPointProvider.php';
require_once __DIR__.'/../../dkmodul/class/AccessPoint/DkAccessPointOutboundDocument.php';
require_once __DIR__.'/../../dkmodul/class/AccessPoint/DkAccessPointMessageResult.php';
require_once __DIR__.'/../../dkmodul/class/EInvoice/Transport/OutboundEnvelope.php';
require_once __DIR__.'/../../dkmodul/class/EInvoice/Transport/TransportReceipt.php';

final class FakeOutboundAccessPoint implements DkAccessPointProvider
{
    public $documents = array();

    public function capabilities() {}
    public function health() {}
    public function connect(DkAccessPointConnection $connection) {}
    public function disconnect() {}
    public function registerCompany(DkAccessPointCompany $company) {}
    public function registrationStatus($registrationId) {}
    public function outboundStatus(DkAccessPointMessageReference $reference) {}
    public function listOutbound(DkAccessPointPollCursor $cursor) {}
    public function listInbound(DkAccessPointPollCursor $cursor) {}
    public function downloadInbound(DkAccessPointMessageReference $reference) {}
    public function markInboundHandled(DkAccessPointMessageReference $reference) {}

    public function send(DkAccessPointOutboundDocument $document)
    {
        $this->documents[] = $document;
        return new DkAccessPointMessageResult(
            true,
            $document->documentId(),
            'provider-123',
            array('provider' => 'inexchange')
        );
    }
}

$provider = new FakeOutboundAccessPoint();
$adapter = new DkAccessPointTransportAdapter($provider, 'OIOUBL');
$payload = '<Invoice>test</Invoice>';
$envelope = new DkOutboundEnvelope(
    'delivery-123',
    'erp-invoice-123',
    'inexchange',
    '0088',
    '5798000000000',
    'application/xml',
    hash('sha256', $payload),
    $payload
);

$receipt = $adapter->send($envelope);

if (!$receipt instanceof DkTransportReceipt || $receipt->status !== 'accepted') {
    throw new RuntimeException('Access Point outbound send did not produce an accepted receipt');
}
if ($receipt->providerMessageId !== 'provider-123') {
    throw new RuntimeException('Access Point provider reference was not preserved');
}
if (count($provider->documents) !== 1) {
    throw new RuntimeException('Access Point provider was not called exactly once');
}

$document = $provider->documents[0];
if ($document->documentId() !== 'delivery-123'
    || $document->format() !== 'OIOUBL'
    || $document->payload() !== $payload
    || $document->idempotencyKey() !== 'erp-invoice-123') {
    throw new RuntimeException('Access Point outbound document mapping failed');
}

$recipient = $document->recipient();
if ($recipient['scheme'] !== '0088' || $recipient['id'] !== '5798000000000') {
    throw new RuntimeException('Access Point recipient mapping failed');
}

echo "Access Point outbound send bridge: PASS\n";
