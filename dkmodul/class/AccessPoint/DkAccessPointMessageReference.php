<?php

final class DkAccessPointMessageReference
{
    private $documentId;
    private $providerReference;
    private $idempotencyKey;

    public function __construct($documentId, $providerReference = null, $idempotencyKey = null)
    {
        if (!is_string($documentId) || trim($documentId) === '') {
            throw new InvalidArgumentException('Access Point document id is required');
        }

        $this->documentId = trim($documentId);
        $this->providerReference = $providerReference === null ? null : trim((string) $providerReference);
        $this->idempotencyKey = $idempotencyKey === null ? null : trim((string) $idempotencyKey);
    }

    public function documentId()
    {
        return $this->documentId;
    }

    public function providerReference()
    {
        return $this->providerReference;
    }

    public function idempotencyKey()
    {
        return $this->idempotencyKey;
    }
}
