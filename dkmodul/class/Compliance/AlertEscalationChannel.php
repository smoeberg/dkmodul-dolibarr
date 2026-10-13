<?php

interface DkAlertEscalationChannel
{
    /**
     * Escalate an alert after primary delivery exhaustion.
     * Implementations MUST throw when escalation cannot be positively confirmed.
     */
    public function escalate(array $alert, $attemptCount);
}
