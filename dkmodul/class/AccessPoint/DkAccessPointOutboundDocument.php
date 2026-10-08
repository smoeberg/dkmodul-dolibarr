<?php

final class DkAccessPointOutboundDocument
{
    private $documentId;
    private $format;
    private $payload;
    private $recipient;
    private $idempotencyKey;

    public function __construct($documentId, $format, $payload, array $recipient, $idempotencyKey = null)
    {
        foreach (array('documentId' => $documentId, 'format' => $format, 'payload' => $payload) as $field => $value) {
            if (!is_string($value) || trim($value) === '') {
                throw new InvalidArgumentException('Outbound document '.$field.' is required');
            }
        }

        $this->documentId = trim($documentId);
        $this->format = trim($format);
        $this->payload = $payload;
        $this->recipient = $recipient;
        $this->idempotencyKey = $idempotencyKey === null ? null : trim((string) $idempotencyKey);
    }

    public function documentId()
    {
        return $this->documentId;
    }

    public function format()
    {
        return $this->format;
    }

    public function payload()
    {
        return $this->payload;
    }

    public function recipient()
    {
        return $this->recipient;
    }

    public function idempotencyKey()
    {
        return $this->idempotencyKey;
    }
}
