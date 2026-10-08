<?php

require_once dirname(__DIR__, 2).'/AccessPoint/DkAccessPointProvider.php';
require_once dirname(__DIR__, 2).'/AccessPoint/DkAccessPointMessageReference.php';
require_once __DIR__.'/DkInboundInvoiceStagingContract.php';
require_once __DIR__.'/InboundOioUblEnvelope.php';
require_once dirname(__DIR__, 2).'/Audit/AuditLedger.php';

final class DkInexchangeInboundStagingService
{
    private $provider;
    private $staging;
    private $audit;

    public function __construct(DkAccessPointProvider $provider, DkInboundInvoiceStagingContract $staging, ?DkAuditLedger $audit = null)
    {
        $this->provider = $provider;
        $this->staging = $staging;
        $this->audit = $audit;
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
                if ($this->audit) {
                    $this->audit->append($entity, 'einvoice.inbound.acknowledgement_attempted', 'dk_einvoice_inbound', (int) $staged['rowid'], $actorId, array(
                        'provider' => 'inexchange',
                        'providerReference' => $providerReference,
                        'reused' => !empty($staged['reused']),
                    ));
                }
                try {
                    $this->provider->markInboundHandled($reference);
                    if ($this->audit) {
                        $this->audit->append($entity, 'einvoice.inbound.acknowledged', 'dk_einvoice_inbound', (int) $staged['rowid'], $actorId, array(
                            'provider' => 'inexchange',
                            'providerReference' => $providerReference,
                            'reused' => !empty($staged['reused']),
                        ));
                    }
                    $result['acknowledged']++;
                } catch (Throwable $ackError) {
                    if ($this->audit) {
                        $this->audit->append($entity, 'einvoice.inbound.acknowledgement_failed', 'dk_einvoice_inbound', (int) $staged['rowid'], $actorId, array(
                            'provider' => 'inexchange',
                            'providerReference' => $providerReference,
                            'reused' => !empty($staged['reused']),
                            'error' => $ackError->getMessage(),
                        ));
                    }
                    throw $ackError;
                }
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
