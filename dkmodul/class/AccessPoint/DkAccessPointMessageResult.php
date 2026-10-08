<?php

final class DkAccessPointMessageResult
{
    private $accepted;
    private $documentId;
    private $providerReference;
    private $metadata;

    public function __construct($accepted, $documentId, $providerReference = null, array $metadata = array())
    {
        if (!is_bool($accepted)) {
            throw new InvalidArgumentException('Access Point send result accepted flag must be boolean');
        }
        if (!is_string($documentId) || trim($documentId) === '') {
            throw new InvalidArgumentException('Access Point send result document id is required');
        }

        $this->accepted = $accepted;
        $this->documentId = trim($documentId);
        $this->providerReference = $providerReference === null ? null : trim((string) $providerReference);
        $this->metadata = $metadata;
    }

    public function accepted() { return $this->accepted; }
    public function documentId() { return $this->documentId; }
    public function providerReference() { return $this->providerReference; }
    public function metadata() { return $this->metadata; }
}
