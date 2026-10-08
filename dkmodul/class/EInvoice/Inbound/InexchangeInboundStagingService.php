<?php

require_once dirname(__DIR__, 2).'/AccessPoint/DkAccessPointProvider.php';
require_once dirname(__DIR__, 2).'/AccessPoint/DkAccessPointMessageReference.php';
require_once __DIR__.'/DkInboundInvoiceStagingContract.php';
require_once __DIR__.'/InboundOioUblEnvelope.php';

final class DkInexchangeInboundStagingService
{
    private $provider;
    private $staging;

    public function __construct(DkAccessPointProvider $provider, DkInboundInvoiceStagingContract $staging)
    {
        $this->provider = $provider;
        $this->staging = $staging;
    }

    public function poll(int $entity, string $storageRoot, int $actorId): array
    {
        if ($entity <= 0 || $storageRoot === '' || $actorId <= 0) {
            throw new InvalidArgumentException('Entity, storage root and actor are required');
        }

        $listed = $this->provider->listInbound(new DkAccessPointPollCursor(null));
        if (!isset($listed['documents']) || !is_array($listed['documents'])) {
            throw new RuntimeException('Inexchange inbound poll did not return documents');
        }

        $result = array(
            'listed' => count($listed['documents']),
            'staged' => 0,
            'acknowledged' => 0,
            'reused' => 0,
            'failed' => array(),
        );

        foreach ($listed['documents'] as $document) {
            $providerReference = $document['DocumentId'] ?? $document['documentId'] ?? null;
            if (!is_string($providerReference) || trim($providerReference) === '') {
                $result['failed'][] = array('documentId' => null, 'error' => 'Inbound document has no provider reference');
                continue;
            }
            $providerReference = trim($providerReference);

            try {
                $reference = new DkAccessPointMessageReference($providerReference, $providerReference);
                $downloaded = $this->provider->downloadInbound($reference);
                $envelope = DkInboundOioUblEnvelope::inspect($downloaded->payload());

                $staged = $this->staging->stage(
                    $entity,
                    'inexchange',
                    $providerReference,
                    $envelope['senderScheme'],
                    $envelope['senderId'],
                    $downloaded->payload(),
                    $actorId
                );
                if (!empty($staged['reused'])) {
                    $result['reused']++;
                } else {
                    $result['staged']++;
                }

                // A provider acknowledgement is only allowed after immutable local persistence.
                $this->provider->markInboundHandled($reference);
                $result['acknowledged']++;
            } catch (Throwable $e) {
                $result['failed'][] = array(
                    'documentId' => $providerReference,
                    'error' => $e->getMessage(),
                );
            }
        }

        return $result;
    }
}
