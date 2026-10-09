<?php

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);

require '/var/www/html/main.inc.php';
require '/var/www/html/custom/dkmodul/class/EInvoice/Transport/OutboundDeliveryService.php';
require '/var/www/html/custom/dkmodul/class/EInvoice/Transport/AccessPointTransportAdapter.php';
require '/var/www/html/custom/dkmodul/class/AccessPoint/DkAccessPointProvider.php';
require '/var/www/html/custom/dkmodul/class/AccessPoint/DkAccessPointOutboundDocument.php';
require '/var/www/html/custom/dkmodul/class/AccessPoint/DkAccessPointMessageResult.php';

final class DkE2EAccessPointProvider implements DkAccessPointProvider
{
    public int $calls = 0;
    public string $payload = '';
    public string $idempotencyKey = '';

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
        $this->calls++;
        $this->payload = $document->payload();
        $this->idempotencyKey = $document->idempotencyKey();

        if ($document->format() !== 'OIOUBL'
            || $document->recipient()['scheme'] !== 'GLN'
            || $document->recipient()['id'] !== '5790001968502') {
            throw new RuntimeException('Unexpected Access Point outbound mapping');
        }

        return new DkAccessPointMessageResult(
            true,
            $document->documentId(),
            'inexchange-provider-123',
            array('provider' => 'inexchange', 'transport' => 'access-point')
        );
    }
}

$resql = $db->query("SELECT rowid,content_hash FROM ".$db->prefix()."dk_document_archive WHERE entity=1 AND source_type='oioubl_invoice' ORDER BY rowid DESC LIMIT 1");
$document = $resql ? $db->fetch_object($resql) : false;
if (!$document) {
    throw new RuntimeException('Archived OIOUBL transport source is missing');
}

$service = new DkOutboundDeliveryService($db, '/var/www/documents/dkmodul/archive');
$queued = $service->queue(1, (int) $document->rowid, 'inexchange', 'GLN', '5790001968502', 1);

$provider = new DkE2EAccessPointProvider();
$adapter = new DkAccessPointTransportAdapter($provider, 'OIOUBL');
$sent = $service->dispatch(1, $queued['rowid'], $adapter, 1);

if ($sent['state'] !== 'accepted' || $sent['attempt'] !== 1) {
    throw new RuntimeException('Access Point outbound delivery was not accepted');
}
if ($provider->calls !== 1) {
    throw new RuntimeException('Access Point provider was not called exactly once');
}
if (!hash_equals((string) $document->content_hash, hash('sha256', $provider->payload))) {
    throw new RuntimeException('Access Point provider did not receive exact archived bytes');
}
if ($provider->idempotencyKey !== $queued['idempotencyKey']) {
    throw new RuntimeException('Access Point idempotency key was not preserved');
}

$reused = $service->dispatch(1, $queued['rowid'], $adapter, 1);
if ($reused['state'] !== 'accepted' || !$reused['reused'] || $provider->calls !== 1) {
    throw new RuntimeException('Accepted Access Point delivery was not reused idempotently');
}

$resql = $db->query('SELECT provider_message_id,receipt_code FROM '.$db->prefix().'dk_einvoice_transport_event WHERE delivery_rowid='.(int) $queued['rowid'].' AND event_type=\'accepted\' ORDER BY rowid DESC LIMIT 1');
$receipt = $resql ? $db->fetch_object($resql) : false;
if (!$receipt || $receipt->provider_message_id !== 'inexchange-provider-123' || $receipt->receipt_code !== 'ACCEPTED') {
    throw new RuntimeException('Access Point provider receipt was not persisted');
}

echo 'Access Point outbound E2E delivery: PASS\\n';
