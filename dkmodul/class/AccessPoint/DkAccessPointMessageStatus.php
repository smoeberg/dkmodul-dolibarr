<?php

final class DkAccessPointMessageStatus
{
    private $status;
    private $documentId;
    private $providerStatus;
    private $updatedAt;

    public function __construct($status, $documentId, $providerStatus = null, $updatedAt = null)
    {
        $allowed = array('PENDING', 'SENT', 'DELIVERED', 'FAILED', 'STOPPED');
        $status = strtoupper((string) $status);
        if (!in_array($status, $allowed, true)) {
            throw new InvalidArgumentException('Invalid normalized Access Point message status');
        }
        if (!is_string($documentId) || trim($documentId) === '') {
            throw new InvalidArgumentException('Access Point message status document id is required');
        }

        $this->status = $status;
        $this->documentId = trim($documentId);
        $this->providerStatus = $providerStatus === null ? null : trim((string) $providerStatus);
        $this->updatedAt = $updatedAt === null ? null : trim((string) $updatedAt);
    }

    public function status() { return $this->status; }
    public function documentId() { return $this->documentId; }
    public function providerStatus() { return $this->providerStatus; }
    public function updatedAt() { return $this->updatedAt; }
}
