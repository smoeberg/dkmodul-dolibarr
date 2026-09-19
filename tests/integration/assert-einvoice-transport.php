<?php

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);

require '/var/www/html/main.inc.php';
require '/var/www/html/custom/dkmodul/class/EInvoice/Transport/OutboundDeliveryService.php';

final class DkDeterministicTransportAdapter implements DkTransportAdapterInterface
{
    public int $calls = 0;
    public string $payloadHash = '';

    public function send(DkOutboundEnvelope $envelope): DkTransportReceipt
    {
        $this->calls++;
        $this->payloadHash = hash('sha256', $envelope->payload);
        if ($envelope->endpointScheme !== 'GLN' || $envelope->endpointId !== '5790001968502') {
            throw new RuntimeException('Unexpected integration recipient endpoint');
        }
        return new DkTransportReceipt(
            'accepted', 'fake-provider-'.$envelope->deliveryUuid, 'DELIVERED',
            '2026-09-19 16:00:00',
            array('adapter' => 'deterministic-fake', 'contentHash' => $envelope->contentHash)
        );
    }
}

$resql = $db->query("SELECT rowid,content_hash FROM ".$db->prefix()."dk_document_archive WHERE entity=1 AND source_type='oioubl_invoice' ORDER BY rowid DESC LIMIT 1");
$document = $resql ? $db->fetch_object($resql) : false;
if (!$document) throw new RuntimeException('Archived OIOUBL transport source is missing');

$service = new DkOutboundDeliveryService($db, '/var/www/documents/dkmodul/archive');
$first = $service->queue(1, (int) $document->rowid, 'nemhandel', 'GLN', '5790001968502', 1);
$duplicate = $service->queue(1, (int) $document->rowid, 'nemhandel', 'GLN', '5790001968502', 1);
if ($first['rowid'] !== $duplicate['rowid']) throw new RuntimeException('Delivery queue is not idempotent');

$adapter = new DkDeterministicTransportAdapter();
$sent = $service->dispatch(1, $first['rowid'], $adapter, 1);
$reused = $service->dispatch(1, $first['rowid'], $adapter, 1);
if ($sent['state'] !== 'accepted' || $reused['state'] !== 'accepted' || !$reused['reused'] || $adapter->calls !== 1) {
    throw new RuntimeException('Accepted delivery was not reused idempotently');
}
if (!hash_equals((string) $document->content_hash, $adapter->payloadHash)) {
    throw new RuntimeException('Transport adapter did not receive exact archived bytes');
}

$resql = $db->query('SELECT COUNT(*) AS total FROM '.$db->prefix().'dk_einvoice_transport_event WHERE delivery_rowid='.(int) $first['rowid']);
$count = $resql ? $db->fetch_object($resql) : false;
if (!$count || (int) $count->total !== 2) throw new RuntimeException('Unexpected transport event count');

echo 'Idempotent e-invoice delivery accepted with immutable receipt: '.$first['rowid']."\n";
