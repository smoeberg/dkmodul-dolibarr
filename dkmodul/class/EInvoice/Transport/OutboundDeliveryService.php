<?php

require_once __DIR__.'/TransportAdapterInterface.php';
require_once dirname(__DIR__, 2).'/Audit/AuditLedger.php';

final class DkOutboundDeliveryService
{
    private $db;
    private string $storageRoot;
    private DkAuditLedger $audit;

    public function __construct($db, string $storageRoot, ?DkAuditLedger $audit = null)
    {
        $this->db = $db;
        $this->storageRoot = rtrim($storageRoot, DIRECTORY_SEPARATOR);
        $this->audit = $audit ?: new DkAuditLedger($db);
    }

    public function queue(int $entity, int $documentRowId, string $transport, string $endpointScheme, string $endpointId, int $actorId): array
    {
        if ($entity <= 0 || $documentRowId <= 0 || $actorId <= 0 || $this->storageRoot === '') {
            throw new InvalidArgumentException('Entity, archived document, actor and storage root are required');
        }
        $transport = strtolower(trim($transport));
        $endpointScheme = trim($endpointScheme);
        $endpointId = trim($endpointId);
        if (!preg_match('/^[a-z0-9_-]{2,32}$/', $transport) || $endpointScheme === '' || $endpointId === '') {
            throw new InvalidArgumentException('Transport and recipient endpoint are required');
        }

        $document = $this->document($entity, $documentRowId);
        $this->assertOioUblDocument($document);
        $key = hash('sha256', implode('|', array($entity, $document->document_uuid, $transport, $endpointScheme, $endpointId)));

        $this->db->begin();
        try {
            $existing = $this->findByKey($entity, $key, true);
            if ($existing) {
                $this->db->commit();
                return $this->deliveryResult($entity, $existing);
            }
            $uuid = $this->uuidV4();
            $sql = 'INSERT INTO '.$this->db->prefix().'dk_einvoice_delivery';
            $sql .= ' (entity,delivery_uuid,document_archive_rowid,transport,endpoint_scheme,endpoint_id,idempotency_key,fk_user_author,created_at) VALUES (';
            $sql .= $entity.",'".$this->db->escape($uuid)."',".$documentRowId;
            $sql .= ",'".$this->db->escape($transport)."','".$this->db->escape($endpointScheme)."','".$this->db->escape($endpointId)."','".$key."',".$actorId.',UTC_TIMESTAMP())';
            if (!$this->db->query($sql)) {
                throw new RuntimeException('Unable to queue outbound e-invoice: '.$this->db->lasterror());
            }
            $rowId = (int) $this->db->last_insert_id($this->db->prefix().'dk_einvoice_delivery');
            $this->audit->append($entity, 'einvoice.delivery.queued', 'dk_einvoice_delivery', $rowId, $actorId, array(
                'documentArchiveRowId' => $documentRowId,
                'idempotencyKey' => $key,
                'transport' => $transport,
            ));
            if ($this->db->commit() <= 0) {
                throw new RuntimeException('Unable to commit outbound e-invoice queue');
            }
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }

        return array('rowid' => $rowId, 'deliveryUuid' => $uuid, 'idempotencyKey' => $key, 'state' => 'queued');
    }

    public function dispatch(int $entity, int $deliveryRowId, DkTransportAdapterInterface $adapter, int $actorId): array
    {
        if ($entity <= 0 || $deliveryRowId <= 0 || $actorId <= 0) {
            throw new InvalidArgumentException('Entity, delivery and actor are required');
        }

        $this->db->begin();
        try {
            $delivery = $this->delivery($entity, $deliveryRowId, true);
            $state = $this->state($entity, $deliveryRowId);
            if ($state === 'accepted') {
                $this->db->commit();
                return array('rowid' => $deliveryRowId, 'state' => 'accepted', 'reused' => true);
            }
            $document = $this->document($entity, (int) $delivery->document_archive_rowid);
            $this->assertOioUblDocument($document);
            $payload = $this->readVerifiedPayload($document);
            $attempt = $this->nextAttempt($entity, $deliveryRowId);
            $this->insertEvent($entity, $deliveryRowId, 'attempt', $attempt, '', '', null, array(
                'contentHash' => (string) $document->content_hash,
                'idempotencyKey' => (string) $delivery->idempotency_key,
            ), $actorId);

            try {
                $receipt = $adapter->send(new DkOutboundEnvelope(
                    (string) $delivery->delivery_uuid,
                    (string) $delivery->idempotency_key,
                    (string) $delivery->transport,
                    (string) $delivery->endpoint_scheme,
                    (string) $delivery->endpoint_id,
                    (string) $document->mime_type,
                    (string) $document->content_hash,
                    $payload
                ));
            } catch (Throwable $transportError) {
                $receipt = new DkTransportReceipt('failed', '', 'TRANSPORT_EXCEPTION', gmdate('Y-m-d H:i:s'), array(
                    'exceptionClass' => get_class($transportError),
                    'message' => $transportError->getMessage(),
                ));
            }
            $eventRowId = $this->insertEvent(
                $entity, $deliveryRowId, $receipt->status, $attempt,
                $receipt->providerMessageId, $receipt->receiptCode, $receipt->receiptAt,
                $receipt->evidence, $actorId
            );
            $this->audit->append($entity, 'einvoice.delivery.'.$receipt->status, 'dk_einvoice_delivery', $deliveryRowId, $actorId, array(
                'attempt' => $attempt,
                'providerMessageId' => $receipt->providerMessageId,
                'receiptCode' => $receipt->receiptCode,
                'transportEventRowId' => $eventRowId,
            ));
            if ($this->db->commit() <= 0) {
                throw new RuntimeException('Unable to commit outbound e-invoice receipt');
            }
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }

        return array('rowid' => $deliveryRowId, 'state' => $receipt->status, 'attempt' => $attempt, 'reused' => false);
    }

    private function document(int $entity, int $rowId)
    {
        $sql = 'SELECT rowid,document_uuid,source_type,mime_type,storage_key,content_hash,byte_size';
        $sql .= ' FROM '.$this->db->prefix().'dk_document_archive WHERE entity='.$entity.' AND rowid='.$rowId;
        $resql = $this->db->query($sql);
        $row = $resql ? $this->db->fetch_object($resql) : false;
        if (!$row) throw new InvalidArgumentException('Archived OIOUBL document was not found');
        return $row;
    }

    private function assertOioUblDocument($document): void
    {
        if (!in_array($document->source_type, array('oioubl_invoice', 'oioubl_credit_note'), true) || $document->mime_type !== 'application/xml') {
            throw new InvalidArgumentException('Only archived OIOUBL invoice or credit-note XML can be queued');
        }
    }

    private function readVerifiedPayload($document): string
    {
        $path = $this->storageRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $document->storage_key);
        $payload = is_file($path) ? file_get_contents($path) : false;
        if ($payload === false || strlen($payload) !== (int) $document->byte_size
            || !hash_equals((string) $document->content_hash, hash('sha256', $payload))) {
            throw new RuntimeException('Archived OIOUBL bytes failed integrity verification');
        }
        return $payload;
    }

    private function findByKey(int $entity, string $key, bool $lock)
    {
        $sql = 'SELECT rowid,delivery_uuid,idempotency_key FROM '.$this->db->prefix().'dk_einvoice_delivery';
        $sql .= " WHERE entity=".$entity." AND idempotency_key='".$key."'".($lock ? ' FOR UPDATE' : '');
        $resql = $this->db->query($sql);
        return $resql ? $this->db->fetch_object($resql) : false;
    }

    private function delivery(int $entity, int $rowId, bool $lock)
    {
        $sql = 'SELECT * FROM '.$this->db->prefix().'dk_einvoice_delivery WHERE entity='.$entity.' AND rowid='.$rowId.($lock ? ' FOR UPDATE' : '');
        $resql = $this->db->query($sql);
        $row = $resql ? $this->db->fetch_object($resql) : false;
        if (!$row) throw new InvalidArgumentException('Outbound e-invoice delivery was not found');
        return $row;
    }

    private function deliveryResult(int $entity, $row): array
    {
        return array(
            'rowid' => (int) $row->rowid,
            'deliveryUuid' => (string) $row->delivery_uuid,
            'idempotencyKey' => (string) $row->idempotency_key,
            'state' => $this->state($entity, (int) $row->rowid),
        );
    }

    private function state(int $entity, int $deliveryRowId): string
    {
        $sql = 'SELECT event_type FROM '.$this->db->prefix().'dk_einvoice_transport_event';
        $sql .= ' WHERE entity='.$entity.' AND delivery_rowid='.$deliveryRowId." AND event_type<>'attempt' ORDER BY rowid DESC LIMIT 1";
        $resql = $this->db->query($sql);
        $row = $resql ? $this->db->fetch_object($resql) : false;
        return $row ? (string) $row->event_type : 'queued';
    }

    private function nextAttempt(int $entity, int $deliveryRowId): int
    {
        $sql = 'SELECT COALESCE(MAX(attempt_number),0)+1 AS next_attempt FROM '.$this->db->prefix().'dk_einvoice_transport_event';
        $sql .= ' WHERE entity='.$entity.' AND delivery_rowid='.$deliveryRowId;
        $resql = $this->db->query($sql);
        $row = $resql ? $this->db->fetch_object($resql) : false;
        return $row ? (int) $row->next_attempt : 1;
    }

    private function insertEvent(int $entity, int $deliveryRowId, string $eventType, int $attempt, string $providerId, string $code, ?string $receiptAt, array $evidence, int $actorId): int
    {
        $json = $this->canonicalJson(array(
            'eventType' => $eventType,
            'providerMessageId' => $providerId,
            'receiptAt' => $receiptAt,
            'receiptCode' => $code,
            'evidence' => $evidence,
        ));
        $sql = 'INSERT INTO '.$this->db->prefix().'dk_einvoice_transport_event';
        $sql .= ' (entity,delivery_rowid,event_type,attempt_number,provider_message_id,receipt_code,receipt_at,receipt_hash,receipt_json,fk_user_author,created_at) VALUES (';
        $sql .= $entity.','.$deliveryRowId.",'".$eventType."',".$attempt;
        $sql .= ",'".$this->db->escape($providerId)."','".$this->db->escape($code)."',";
        $sql .= $receiptAt === null ? 'NULL,' : "'".$this->db->escape($receiptAt)."',";
        $sql .= "'".hash('sha256', $json)."','".$this->db->escape($json)."',".$actorId.',UTC_TIMESTAMP())';
        if (!$this->db->query($sql)) {
            throw new RuntimeException('Unable to record e-invoice transport event: '.$this->db->lasterror());
        }
        return (int) $this->db->last_insert_id($this->db->prefix().'dk_einvoice_transport_event');
    }

    private function canonicalJson(array $value): string
    {
        $this->recursiveKsort($value);
        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function recursiveKsort(array &$value): void
    {
        ksort($value);
        foreach ($value as &$item) {
            if (is_array($item)) $this->recursiveKsort($item);
        }
    }

    private function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
