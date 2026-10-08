<?php

require_once __DIR__.'/../../dkmodul/class/AccessPoint/DkAccessPointProvider.php';
require_once __DIR__.'/../../dkmodul/class/AccessPoint/DkAccessPointConnection.php';
require_once __DIR__.'/../../dkmodul/class/AccessPoint/DkAccessPointCompany.php';
require_once __DIR__.'/../../dkmodul/class/AccessPoint/DkAccessPointOutboundDocument.php';
require_once __DIR__.'/../../dkmodul/class/AccessPoint/DkAccessPointMessageReference.php';
require_once __DIR__.'/../../dkmodul/class/AccessPoint/DkAccessPointPollCursor.php';
require_once __DIR__.'/../../dkmodul/class/AccessPoint/DkAccessPointDocument.php';
require_once __DIR__.'/../../dkmodul/class/EInvoice/Inbound/DkInboundInvoiceStagingContract.php';
require_once __DIR__.'/../../dkmodul/class/EInvoice/Inbound/InboundOioUblEnvelope.php';
require_once __DIR__.'/../../dkmodul/class/EInvoice/Inbound/InexchangeInboundStagingService.php';

final class FakeInboundProvider implements DkAccessPointProvider
{
    public $handled = array();

    public function capabilities() { return null; }
    public function health() { return null; }
    public function connect(DkAccessPointConnection $connection) {}
    public function disconnect() {}
    public function registerCompany(DkAccessPointCompany $company) { return null; }
    public function registrationStatus($registrationId) { return null; }
    public function send(DkAccessPointOutboundDocument $document) { return null; }
    public function outboundStatus(DkAccessPointMessageReference $reference) { return null; }

    public function listOutbound(DkAccessPointPollCursor $cursor) { return array('documents' => array()); }

    public function listInbound(DkAccessPointPollCursor $cursor)
    {
        return array('documents' => array(array('DocumentId' => 'in-doc-1')));
    }

    public function downloadInbound(DkAccessPointMessageReference $reference)
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
 xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
 xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
  <cac:AccountingSupplierParty><cac:Party><cbc:EndpointID schemeID="GLN">5798000000000</cbc:EndpointID></cac:Party></cac:AccountingSupplierParty>
</Invoice>';
        return new DkAccessPointDocument($reference->documentId(), $xml, 'OIOUBL');
    }

    public function markInboundHandled(DkAccessPointMessageReference $reference)
    {
        $this->handled[] = $reference->providerReference();
        return true;
    }
}

final class FakeInboundStaging implements DkInboundInvoiceStagingContract
{
    public $calls = 0;
    public $fail = false;
    public $reused = false;

    public function stage(int $entity, string $channel, string $providerMessageId, string $senderScheme, string $senderId, string $xml, int $actorId): array
    {
        $this->calls++;
        if ($this->fail) throw new RuntimeException('simulated staging failure');
        return array('rowid' => 1, 'state' => 'received', 'reused' => $this->reused);
    }
}

$provider = new FakeInboundProvider();
$staging = new FakeInboundStaging();
$service = new DkInexchangeInboundStagingService($provider, $staging);
$result = $service->poll(1, '/tmp/dkmodul-inbound-test', 42);

if ($result['staged'] !== 1 || $result['reused'] !== 0 || $result['acknowledged'] !== 1 || count($provider->handled) !== 1) {
    throw new RuntimeException('Successful inbound staging was not acknowledged exactly once');
}

$provider = new FakeInboundProvider();
$staging = new FakeInboundStaging();
$staging->fail = true;
$service = new DkInexchangeInboundStagingService($provider, $staging);
$result = $service->poll(1, '/tmp/dkmodul-inbound-test', 42);

if ($result['staged'] !== 0 || $result['acknowledged'] !== 0 || count($provider->handled) !== 0) {
    throw new RuntimeException('Inbound document was acknowledged after staging failure');
}
if (count($result['failed']) !== 1) {
    throw new RuntimeException('Inbound staging failure was not reported');
}

$provider = new FakeInboundProvider();
$staging = new FakeInboundStaging();
$service = new DkInexchangeInboundStagingService($provider, $staging);
$first = $service->poll(1, '/tmp/dkmodul-inbound-test', 42);
$staging->reused = true;
$second = $service->poll(1, '/tmp/dkmodul-inbound-test', 42);
if ($first['staged'] !== 1 || $second['staged'] !== 0 || $second['reused'] !== 1 || $second['acknowledged'] !== 1 || count($provider->handled) !== 2) {
    throw new RuntimeException('Inbound retry was not reported as idempotent reuse');
}

echo "Inexchange inbound no-ack-on-staging-failure contract: PASS\n";
