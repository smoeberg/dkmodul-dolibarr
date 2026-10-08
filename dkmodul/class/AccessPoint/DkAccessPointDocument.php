<?php

final class DkAccessPointDocument
{
    private $documentId;
    private $payload;
    private $format;
    private $metadata;

    public function __construct($documentId, $payload, $format = null, array $metadata = array())
    {
        if (!is_string($documentId) || trim($documentId) === '') {
            throw new InvalidArgumentException('Access Point document id is required');
        }
        if (!is_string($payload) || $payload === '') {
            throw new InvalidArgumentException('Access Point document payload is required');
        }

        $this->documentId = trim($documentId);
        $this->payload = $payload;
        $this->format = $format === null ? null : trim((string) $format);
        $this->metadata = $metadata;
    }

    public function documentId() { return $this->documentId; }
    public function payload() { return $this->payload; }
    public function format() { return $this->format; }
    public function metadata() { return $this->metadata; }
}
