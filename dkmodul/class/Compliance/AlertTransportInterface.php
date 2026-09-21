<?php

interface DkAlertTransportInterface
{
    /**
     * Delivers a provider-neutral alert payload. Implementations may use webhook, mail or an outbox consumer.
     */
    public function deliver(array $alert);
}
