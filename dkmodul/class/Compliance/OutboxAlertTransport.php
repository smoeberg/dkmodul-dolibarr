<?php

require_once __DIR__.'/AlertTransportInterface.php';

final class DkOutboxAlertTransport implements DkAlertTransportInterface
{
    public function deliver(array $alert)
    {
        return array(
            'channel' => 'outbox',
            'status' => 'queued',
            'external_reference' => $alert['alert_uuid'],
        );
    }
}
