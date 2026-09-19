<?php

require_once __DIR__.'/OutboundEnvelope.php';
require_once __DIR__.'/TransportReceipt.php';

interface DkTransportAdapterInterface
{
    public function send(DkOutboundEnvelope $envelope): DkTransportReceipt;
}
