<?php

final readonly class DkOutboundEnvelope
{
    public function __construct(
        public string $deliveryUuid,
        public string $idempotencyKey,
        public string $transport,
        public string $endpointScheme,
        public string $endpointId,
        public string $contentType,
        public string $contentHash,
        public string $payload
    ) {
        foreach (array('deliveryUuid', 'idempotencyKey', 'transport', 'endpointScheme', 'endpointId', 'contentType', 'contentHash') as $field) {
            if (trim($this->{$field}) === '') {
                throw new InvalidArgumentException('Outbound envelope requires '.$field);
            }
        }
        if ($payload === '' || !hash_equals($contentHash, hash('sha256', $payload))) {
            throw new InvalidArgumentException('Outbound envelope payload does not match its content hash');
        }
    }
}
