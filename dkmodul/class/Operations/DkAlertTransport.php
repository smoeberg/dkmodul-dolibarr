<?php

/**
 * Production alert transport boundary.
 *
 * The application supplies a structured control failure; deployment-specific
 * transports implement delivery. A transport must throw when delivery cannot
 * be confirmed, so callers cannot mistake a dropped alert for a delivered one.
 */
interface DkAlertTransport
{
    /**
     * @param array<string,mixed> $alert
     * @return string delivery identifier
     */
    public function send(array $alert): string;
}
