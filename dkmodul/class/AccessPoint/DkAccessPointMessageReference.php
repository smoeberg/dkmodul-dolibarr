<?php

final class DkAccessPointMessageReference
{
    private $documentId;
    private $providerReference;

    public function __construct($documentId, $providerReference = null)
    {
        if (!is_string($documentId) || trim($documentId) === '') {
            throw new InvalidArgumentException('Access Point document id is required');
        }

        $this->documentId = trim($documentId);
        $this->providerReference = $providerReference === null ? null : trim((string) $providerReference);
    }

    public function documentId()
    {
        return $this->documentId;
    }

    public function providerReference()
    {
        return $this->providerReference;
    }
}
