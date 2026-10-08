<?php

require_once dirname(__DIR__, 2).'/AccessPoint/DkAccessPointProvider.php';
require_once dirname(__DIR__, 2).'/AccessPoint/DkAccessPointOutboundDocument.php';
require_once dirname(__DIR__, 2).'/AccessPoint/DkAccessPointMessageResult.php';
require_once __DIR__.'/OutboundEnvelope.php';
require_once __DIR__.'/TransportReceipt.php';
require_once __DIR__.'/TransportAdapterInterface.php';

final class DkAccessPointTransportAdapter implements DkTransportAdapterInterface
{
    private $provider;
    private $documentFormat;

    public function __construct(DkAccessPointProvider $provider, $documentFormat = 'OIOUBL')
    {
        $this->provider = $provider;
        $this->documentFormat = trim((string) $documentFormat);
        if ($this->documentFormat === '') {
            throw new InvalidArgumentException('Access Point document format is required');
        }
    }

    public function send(DkOutboundEnvelope $envelope): DkTransportReceipt
    {
        $result = $this->provider->send(new DkAccessPointOutboundDocument(
            $envelope->deliveryUuid,
            $this->documentFormat,
            $envelope->payload,
            array(
                'scheme' => $envelope->endpointScheme,
                'id' => $envelope->endpointId,
            ),
            $envelope->idempotencyKey
        ));

        if (!$result instanceof DkAccessPointMessageResult || !$result->accepted()) {
            throw new RuntimeException('Access Point outbound send was not accepted');
        }

        $providerReference = trim((string) $result->providerReference());
        if ($providerReference === '') {
            throw new RuntimeException('Access Point outbound send did not return a provider reference');
        }

        return new DkTransportReceipt(
            'accepted',
            $providerReference,
            'ACCEPTED',
            gmdate('Y-m-d H:i:s'),
            array(
                'provider' => $envelope->transport,
                'providerReference' => $providerReference,
                'documentId' => $result->documentId(),
                'metadata' => $result->metadata(),
            )
        );
    }
}
