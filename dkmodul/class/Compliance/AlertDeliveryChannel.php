<?php

interface DkAlertDeliveryChannel
{
    /**
     * Deliver one operational alert and return a provider delivery reference.
     * Implementations MUST throw when delivery cannot be positively confirmed.
     */
    public function send(array $alert);
}
