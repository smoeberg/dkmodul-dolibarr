<?php

require_once dirname(__DIR__).'/OioUblValidator.php';
require_once dirname(__DIR__, 2).'/Audit/AuditLedger.php';

final class DkApplicationResponseService
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

    public function receive(
        int $entity,
        int $deliveryRowId,
        string $channel,
        string $providerMessageId,
        string $senderScheme,
        string $senderId,
        string $xml,
        string $schemaPath,
        string $schematronResultXml,
        int $actorId
    ): array {
        $channel = strtolower(trim($channel));
        $providerMessageId = trim($providerMessageId);
        $senderScheme = trim($senderScheme);
        $senderId = trim($senderId);
        if ($entity <= 0 || $deliveryRowId <= 0 || $actorId <= 0 || $this->storageRoot === '' || $xml === '' || !is_file($schemaPath)) {
            throw new InvalidArgumentException('Entity, delivery, actor, storage root, response XML and official schema are required');
        }
        if (!preg_match('/^[a-z0-9_-]{2,32}$/', $channel) || $providerMessageId === '' || $senderScheme === '' || $senderId === '') {
            throw new InvalidArgumentException('Response channel and message identity are required');
        }

        $contentHash = hash('sha256', $xml);
        $messageKey = hash('sha256', implode('|', array($entity, $channel, $providerMessageId)));
        $existing = $this->findByMessageKey($entity, $messageKey, false);
        if ($existing) return $this->reuse($existing, $contentHash);

        $validator = new DkOioUblValidator();
        $validator->validateXsd($xml, $schemaPath);
        $validator->validateSchematronResult($schematronResultXml);
        $metadata = $this->metadata($xml);
        $delivery = $this->delivery($entity, $deliveryRowId, false);
        $document = $this->document($entity, (int) $delivery->document_archive_rowid);
        $this->assertBinding($metadata, $delivery, $document, $senderScheme, $senderId);

        $schemaHash = (string) hash_file('sha256', $schemaPath);
        $schematronHash = hash('sha256', $schematronResultXml);
        $evidence = array(
            'contentHash' => $contentHash,
            'deliveryUuid' => (string) $delivery->delivery_uuid,
            'documentArchiveRowId' => (int) $document->rowid,
            'schemaHash' => $schemaHash,
            'schematronResultHash' => $schematronHash,
        );
        $evidenceJson = $this->canonicalJson($evidence);
        $storageKey = $entity.'/responses/'.substr($contentHash, 0, 2).'/'.$contentHash.'.xml';
        $target = $this->storageRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $storageKey);
        $createdFile = $this->storeImmutable($xml, $target, $contentHash);

        $this->db->begin();
        try {
            $existing = $this->findByMessageKey($entity, $messageKey, true);
            if ($existing) {
                $this->db->commit();
                return $this->reuse($existing, $contentHash);
            }
            $responseUuid = $this->uuidV4();
            $sql = 'INSERT INTO '.$this->db->prefix().'dk_einvoice_application_response';
            $sql .= ' (entity,response_uuid,delivery_rowid,channel,provider_message_id,message_key,sender_endpoint_scheme,sender_endpoint_id,response_id,response_document_uuid,issue_date,response_code,description,referenced_document_id,referenced_document_uuid,referenced_document_type,storage_key,content_hash,byte_size,schema_hash,schematron_result_hash,evidence_hash,evidence_json,fk_user_author,received_at) VALUES (';
            $sql .= $entity.",'".$this->db->escape($responseUuid)."',".$deliveryRowId;
            foreach (array($channel, $providerMessageId, $messageKey, $senderScheme, $senderId, $metadata['responseId'], $metadata['responseUuid'], $metadata['issueDate'], $metadata['responseCode']) as $value) {
                $sql .= ",'".$this->db->escape($value)."'";
            }
            $sql .= $metadata['description'] === '' ? ',NULL' : ",'".$this->db->escape($metadata['description'])."'";
            foreach (array($metadata['documentId'], $metadata['documentUuid'], $metadata['documentType'], $storageKey, $contentHash) as $value) {
                $sql .= ",'".$this->db->escape($value)."'";
            }
            $sql .= ','.strlen($xml).",'".$schemaHash."','".$schematronHash."','".hash('sha256', $evidenceJson)."','".$this->db->escape($evidenceJson)."',".$actorId.',UTC_TIMESTAMP())';
            if (!$this->db->query($sql)) throw new RuntimeException('Unable to record OIOUBL application response: '.$this->db->lasterror());
            $rowId = (int) $this->db->last_insert_id($this->db->prefix().'dk_einvoice_application_response');
            $state = $this->state($metadata['responseCode']);
            $this->audit->append($entity, 'einvoice.response.'.$state, 'dk_einvoice_application_response', $rowId, $actorId, array(
                'contentHash' => $contentHash,
                'deliveryRowId' => $deliveryRowId,
                'evidenceHash' => hash('sha256', $evidenceJson),
                'responseCode' => $metadata['responseCode'],
            ));
            if ($this->db->commit() <= 0) throw new RuntimeException('Unable to commit OIOUBL application response');
        } catch (Throwable $e) {
            $this->db->rollback();
            if ($createdFile && is_file($target)) @unlink($target);
            throw $e;
        }

        return array('rowid' => $rowId, 'responseUuid' => $responseUuid, 'responseCode' => $metadata['responseCode'], 'state' => $state, 'reused' => false);
    }

    private function metadata(string $xml): array
    {
        $dom = new DOMDocument();
        if (!$dom->loadXML($xml, LIBXML_NONET) || !$dom->documentElement || $dom->documentElement->localName !== 'ApplicationResponse') {
            throw new InvalidArgumentException('Validated response must be an OIOUBL ApplicationResponse');
        }
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('doc', 'urn:oasis:names:specification:ubl:schema:xsd:ApplicationResponse-2');
        $xp->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xp->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $value = static fn(string $path): string => trim((string) $xp->evaluate('string('.$path.')'));
        $base = '/doc:ApplicationResponse';
        $metadata = array(
            'responseId' => $value($base.'/cbc:ID'),
            'responseUuid' => $value($base.'/cbc:UUID'),
            'issueDate' => $value($base.'/cbc:IssueDate'),
            'senderEndpoint' => $value($base.'/cac:SenderParty/cbc:EndpointID'),
            'senderScheme' => $value($base.'/cac:SenderParty/cbc:EndpointID/@schemeID'),
            'receiverEndpoint' => $value($base.'/cac:ReceiverParty/cbc:EndpointID'),
            'responseCode' => $value($base.'/cac:DocumentResponse/cac:Response/cbc:ResponseCode'),
            'description' => $value($base.'/cac:DocumentResponse/cac:Response/cbc:Description'),
            'documentId' => $value($base.'/cac:DocumentResponse/cac:DocumentReference/cbc:ID'),
            'documentUuid' => $value($base.'/cac:DocumentResponse/cac:DocumentReference/cbc:UUID'),
            'documentType' => $value($base.'/cac:DocumentResponse/cac:DocumentReference/cbc:DocumentTypeCode'),
        );
        foreach ($metadata as $field => $value) {
            if ($field !== 'description' && $value === '') throw new InvalidArgumentException('OIOUBL application response is missing '.$field);
        }
        if (!in_array($metadata['responseCode'], array('BusinessAccept', 'BusinessReject', 'ProfileReject', 'TechnicalAccept', 'TechnicalReject'), true)) {
            throw new InvalidArgumentException('Unsupported OIOUBL application response code');
        }
        return $metadata;
    }

    private function assertBinding(array $metadata, $delivery, $document, string $senderScheme, string $senderId): void
    {
        if ($metadata['senderEndpoint'] !== $senderId || $metadata['senderScheme'] !== $senderScheme
            || (string) $delivery->endpoint_id !== $senderId || (string) $delivery->endpoint_scheme !== $senderScheme) {
            throw new RuntimeException('Application response sender does not match delivered recipient');
        }
        $payload = $this->readVerifiedDocument($document);
        $dom = new DOMDocument();
        if (!$dom->loadXML($payload, LIBXML_NONET) || !$dom->documentElement) throw new RuntimeException('Archived outbound OIOUBL cannot be parsed');
        $type = $dom->documentElement->localName;
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('doc', 'urn:oasis:names:specification:ubl:schema:xsd:'.$type.'-2');
        $xp->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xp->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $base = '/doc:'.$type;
        $id = trim((string) $xp->evaluate('string('.$base.'/cbc:ID)'));
        $uuid = trim((string) $xp->evaluate('string('.$base.'/cbc:UUID)'));
        $supplierEndpoint = trim((string) $xp->evaluate('string('.$base.'/cac:AccountingSupplierParty/cac:Party/cbc:EndpointID)'));
        if ($metadata['documentId'] !== $id || $metadata['documentUuid'] !== $uuid || $metadata['documentType'] !== $type
            || $metadata['receiverEndpoint'] !== $supplierEndpoint) {
            throw new RuntimeException('Application response does not reference the delivered OIOUBL document and parties');
        }
    }

    private function delivery(int $entity, int $rowId, bool $lock)
    {
        $sql = 'SELECT * FROM '.$this->db->prefix().'dk_einvoice_delivery WHERE entity='.$entity.' AND rowid='.$rowId.($lock ? ' FOR UPDATE' : '');
        $resql = $this->db->query($sql);
        $row = $resql ? $this->db->fetch_object($resql) : false;
        if (!$row) throw new InvalidArgumentException('Outbound OIOUBL delivery was not found');
        return $row;
    }

    private function document(int $entity, int $rowId)
    {
        $sql = 'SELECT rowid,source_type,mime_type,storage_key,content_hash,byte_size FROM '.$this->db->prefix().'dk_document_archive WHERE entity='.$entity.' AND rowid='.$rowId;
        $resql = $this->db->query($sql);
        $row = $resql ? $this->db->fetch_object($resql) : false;
        if (!$row || !in_array($row->source_type, array('oioubl_invoice', 'oioubl_credit_note'), true) || $row->mime_type !== 'application/xml') {
            throw new InvalidArgumentException('Delivery does not reference an archived OIOUBL invoice or credit note');
        }
        return $row;
    }

    private function readVerifiedDocument($document): string
    {
        $path = $this->storageRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $document->storage_key);
        $xml = is_file($path) ? file_get_contents($path) : false;
        if ($xml === false || strlen($xml) !== (int) $document->byte_size || !hash_equals((string) $document->content_hash, hash('sha256', $xml))) {
            throw new RuntimeException('Archived outbound OIOUBL bytes failed integrity verification');
        }
        return $xml;
    }

    private function findByMessageKey(int $entity, string $key, bool $lock)
    {
        $sql = 'SELECT rowid,response_uuid,response_code,content_hash FROM '.$this->db->prefix()."dk_einvoice_application_response WHERE entity=".$entity." AND message_key='".$key."'".($lock ? ' FOR UPDATE' : '');
        $resql = $this->db->query($sql);
        return $resql ? $this->db->fetch_object($resql) : false;
    }

    private function reuse($row, string $contentHash): array
    {
        if (!hash_equals((string) $row->content_hash, $contentHash)) throw new RuntimeException('Application response identity was reused with different bytes');
        return array('rowid' => (int) $row->rowid, 'responseUuid' => (string) $row->response_uuid, 'responseCode' => (string) $row->response_code, 'state' => $this->state((string) $row->response_code), 'reused' => true);
    }

    private function state(string $code): string
    {
        return in_array($code, array('BusinessAccept', 'TechnicalAccept'), true) ? 'accepted' : 'rejected';
    }

    private function storeImmutable(string $xml, string $target, string $hash): bool
    {
        if (is_file($target)) {
            if (!hash_equals($hash, (string) hash_file('sha256', $target))) throw new RuntimeException('Application response archive hash collision or corruption');
            return false;
        }
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) throw new RuntimeException('Unable to create application response archive directory');
        $temporary = $target.'.tmp.'.bin2hex(random_bytes(6));
        if (file_put_contents($temporary, $xml, LOCK_EX) === false || !rename($temporary, $target)) {
            @unlink($temporary);
            throw new RuntimeException('Unable to store OIOUBL application response');
        }
        chmod($target, 0440);
        return true;
    }

    private function canonicalJson(array $value): string
    {
        ksort($value);
        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
