<?php

final class DkAccessPointCapabilities
{
    private $documentTypes;
    private $directions;
    private $networks;
    private $webhooks;
    private $registration;

    public function __construct(
        array $documentTypes = array(),
        array $directions = array(),
        array $networks = array(),
        $webhooks = false,
        $registration = false
    ) {
        if (!is_bool($webhooks) || !is_bool($registration)) {
            throw new InvalidArgumentException('Access Point capability flags must be boolean');
        }

        $this->documentTypes = array_values($documentTypes);
        $this->directions = array_values($directions);
        $this->networks = array_values($networks);
        $this->webhooks = $webhooks;
        $this->registration = $registration;
    }

    public function documentTypes() { return $this->documentTypes; }
    public function directions() { return $this->directions; }
    public function networks() { return $this->networks; }
    public function webhooks() { return $this->webhooks; }
    public function registration() { return $this->registration; }
}
