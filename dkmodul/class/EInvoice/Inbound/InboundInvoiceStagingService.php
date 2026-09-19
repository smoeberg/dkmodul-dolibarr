<?php

require_once dirname(__DIR__).'/OioUblValidator.php';
require_once dirname(__DIR__, 2).'/Audit/AuditLedger.php';

final class DkInboundInvoiceStagingService
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

    public function stage(int $entity, string $channel, string $providerMessageId, string $senderScheme, string $senderId, string $xml, int $actorId): array
    {
        $channel = strtolower(trim($channel));
        $providerMessageId = trim($providerMessageId);
        $senderScheme = trim($senderScheme);
        $senderId = trim($senderId);
        if ($entity <= 0 || $actorId <= 0 || $this->storageRoot === '' || $xml === '') {
            throw new InvalidArgumentException('Entity, actor, storage root and inbound XML are required');
        }
        if (!preg_match('/^[a-z0-9_-]{2,32}$/', $channel) || $providerMessageId === '' || $senderScheme === '' || $senderId === '') {
            throw new InvalidArgumentException('Inbound channel and message identity are required');
        }

        $contentHash = hash('sha256', $xml);
        $messageKey = hash('sha256', implode('|', array($entity, $channel, $providerMessageId)));
        $storageKey = $entity.'/'.substr($contentHash, 0, 2).'/'.$contentHash.'.xml';
        $target = $this->storageRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $storageKey);
        $createdFile = $this->storeImmutable($xml, $target, $contentHash);

        $this->db->begin();
        try {
            $existing = $this->findByMessageKey($entity, $messageKey, true);
            if ($existing) {
                if (!hash_equals((string) $existing->content_hash, $contentHash)) {
                    throw new RuntimeException('Inbound message identity was reused with different bytes');
                }
                $this->db->commit();
                return $this->result($entity, $existing);
            }
            $uuid = $this->uuidV4();
            $sql = 'INSERT INTO '.$this->db->prefix().'dk_einvoice_inbound';
            $sql .= ' (entity,inbound_uuid,channel,provider_message_id,sender_endpoint_scheme,sender_endpoint_id,message_key,storage_key,content_hash,byte_size,fk_user_author,received_at) VALUES (';
            $sql .= $entity.",'".$this->db->escape($uuid)."','".$this->db->escape($channel)."','".$this->db->escape($providerMessageId)."'";
            $sql .= ",'".$this->db->escape($senderScheme)."','".$this->db->escape($senderId)."','".$messageKey."','".$this->db->escape($storageKey)."','".$contentHash."',".strlen($xml).','.$actorId.',UTC_TIMESTAMP())';
            if (!$this->db->query($sql)) throw new RuntimeException('Unable to stage inbound OIOUBL: '.$this->db->lasterror());
            $rowId = (int) $this->db->last_insert_id($this->db->prefix().'dk_einvoice_inbound');
            $this->audit->append($entity, 'einvoice.inbound.received', 'dk_einvoice_inbound', $rowId, $actorId, array(
                'channel' => $channel, 'contentHash' => $contentHash, 'messageKey' => $messageKey,
            ));
            if ($this->db->commit() <= 0) throw new RuntimeException('Unable to commit inbound OIOUBL staging');
        } catch (Throwable $e) {
            $this->db->rollback();
            if ($createdFile && is_file($target)) @unlink($target);
            throw $e;
        }

        return array('rowid' => $rowId, 'inboundUuid' => $uuid, 'contentHash' => $contentHash, 'state' => 'received');
    }

    public function validate(int $entity, int $inboundRowId, string $schemaPath, string $schematronResultXml, int $actorId): array
    {
        if ($entity <= 0 || $inboundRowId <= 0 || $actorId <= 0 || !is_file($schemaPath)) {
            throw new InvalidArgumentException('Inbound message, actor and official schema are required');
        }
        $this->db->begin();
        try {
            $inbound = $this->inbound($entity, $inboundRowId, true);
            $existing = $this->validation($entity, $inboundRowId);
            if ($existing) {
                $this->db->commit();
                return array('rowid' => (int) $existing->rowid, 'state' => (string) $existing->event_type, 'reused' => true);
            }
            $xml = $this->readVerifiedPayload($inbound);
            $validator = new DkOioUblValidator();
            $metadata = array();
            $errors = array();
            try {
                $validator->validateXsd($xml, $schemaPath);
                $validator->validateSchematronResult($schematronResultXml);
                $metadata = $this->metadata($xml);
                $eventType = 'validated';
            } catch (Throwable $validationError) {
                $eventType = 'rejected';
                $errors[] = $validationError->getMessage();
            }
            $evidence = array(
                'contentHash' => (string) $inbound->content_hash,
                'errors' => $errors,
                'schemaHash' => (string) hash_file('sha256', $schemaPath),
                'schematronResultHash' => hash('sha256', $schematronResultXml),
            );
            $json = $this->canonicalJson($evidence);
            $sql = 'INSERT INTO '.$this->db->prefix().'dk_einvoice_inbound_validation';
            $sql .= ' (entity,inbound_rowid,event_type,invoice_id,invoice_uuid,issue_date,currency_code,supplier_endpoint,customer_endpoint,payable_amount,evidence_hash,evidence_json,fk_user_author,created_at) VALUES (';
            $sql .= $entity.','.$inboundRowId.",'".$eventType."'";
            $sql .= $this->nullableString($metadata['invoiceId'] ?? null).$this->nullableString($metadata['invoiceUuid'] ?? null);
            $sql .= $this->nullableString($metadata['issueDate'] ?? null).$this->nullableString($metadata['currencyCode'] ?? null);
            $sql .= $this->nullableString($metadata['supplierEndpoint'] ?? null).$this->nullableString($metadata['customerEndpoint'] ?? null);
            $sql .= isset($metadata['payableAmount']) ? ",'".$this->db->escape($metadata['payableAmount'])."'" : ',NULL';
            $sql .= ",'".hash('sha256', $json)."','".$this->db->escape($json)."',".$actorId.',UTC_TIMESTAMP())';
            if (!$this->db->query($sql)) throw new RuntimeException('Unable to record inbound OIOUBL validation: '.$this->db->lasterror());
            $validationRowId = (int) $this->db->last_insert_id($this->db->prefix().'dk_einvoice_inbound_validation');
            $this->audit->append($entity, 'einvoice.inbound.'.$eventType, 'dk_einvoice_inbound', $inboundRowId, $actorId, array(
                'evidenceHash' => hash('sha256', $json), 'validationRowId' => $validationRowId,
            ));
            if ($this->db->commit() <= 0) throw new RuntimeException('Unable to commit inbound OIOUBL validation');
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
        return array('rowid' => $validationRowId, 'state' => $eventType, 'metadata' => $metadata, 'reused' => false);
    }

    private function metadata(string $xml): array
    {
        $dom = new DOMDocument();
        if (!$dom->loadXML($xml, LIBXML_NONET)) throw new InvalidArgumentException('Validated OIOUBL XML cannot be parsed');
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('i', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $value = static fn(string $path): string => trim((string) $xpath->evaluate('string('.$path.')'));
        $metadata = array(
            'invoiceId' => $value('/i:Invoice/cbc:ID'),
            'invoiceUuid' => $value('/i:Invoice/cbc:UUID'),
            'issueDate' => $value('/i:Invoice/cbc:IssueDate'),
            'currencyCode' => $value('/i:Invoice/cbc:DocumentCurrencyCode'),
            'supplierEndpoint' => $value('/i:Invoice/cac:AccountingSupplierParty/cac:Party/cbc:EndpointID'),
            'customerEndpoint' => $value('/i:Invoice/cac:AccountingCustomerParty/cac:Party/cbc:EndpointID'),
            'payableAmount' => $value('/i:Invoice/cac:LegalMonetaryTotal/cbc:PayableAmount'),
        );
        foreach ($metadata as $field => $fieldValue) if ($fieldValue === '') throw new InvalidArgumentException('Validated OIOUBL is missing '.$field);
        return $metadata;
    }

    private function readVerifiedPayload($row): string
    {
        $path = $this->storageRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $row->storage_key);
        $xml = is_file($path) ? file_get_contents($path) : false;
        if ($xml === false || strlen($xml) !== (int) $row->byte_size || !hash_equals((string) $row->content_hash, hash('sha256', $xml))) {
            throw new RuntimeException('Inbound OIOUBL bytes failed integrity verification');
        }
        return $xml;
    }

    private function storeImmutable(string $xml, string $target, string $hash): bool
    {
        if (is_file($target)) {
            if (!hash_equals($hash, (string) hash_file('sha256', $target))) throw new RuntimeException('Inbound archive hash collision or corruption');
            return false;
        }
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) throw new RuntimeException('Unable to create inbound archive directory');
        $temporary = $target.'.tmp.'.bin2hex(random_bytes(6));
        if (file_put_contents($temporary, $xml, LOCK_EX) === false || !rename($temporary, $target)) {
            @unlink($temporary);
            throw new RuntimeException('Unable to store inbound OIOUBL');
        }
        chmod($target, 0440);
        return true;
    }

    private function findByMessageKey(int $entity, string $key, bool $lock)
    {
        $sql = 'SELECT rowid,inbound_uuid,content_hash FROM '.$this->db->prefix()."dk_einvoice_inbound WHERE entity=".$entity." AND message_key='".$key."'".($lock ? ' FOR UPDATE' : '');
        $resql = $this->db->query($sql);
        return $resql ? $this->db->fetch_object($resql) : false;
    }

    private function inbound(int $entity, int $rowId, bool $lock)
    {
        $sql = 'SELECT * FROM '.$this->db->prefix().'dk_einvoice_inbound WHERE entity='.$entity.' AND rowid='.$rowId.($lock ? ' FOR UPDATE' : '');
        $resql = $this->db->query($sql);
        $row = $resql ? $this->db->fetch_object($resql) : false;
        if (!$row) throw new InvalidArgumentException('Inbound OIOUBL staging row was not found');
        return $row;
    }

    private function validation(int $entity, int $inboundRowId)
    {
        $sql = 'SELECT rowid,event_type FROM '.$this->db->prefix().'dk_einvoice_inbound_validation WHERE entity='.$entity.' AND inbound_rowid='.$inboundRowId;
        $resql = $this->db->query($sql);
        return $resql ? $this->db->fetch_object($resql) : false;
    }

    private function result(int $entity, $row): array
    {
        $validation = $this->validation($entity, (int) $row->rowid);
        return array('rowid' => (int) $row->rowid, 'inboundUuid' => (string) $row->inbound_uuid, 'contentHash' => (string) $row->content_hash, 'state' => $validation ? (string) $validation->event_type : 'received');
    }

    private function nullableString(?string $value): string
    {
        return $value === null || $value === '' ? ',NULL' : ",'".$this->db->escape($value)."'";
    }

    private function canonicalJson(array $value): string
    {
        $this->recursiveKsort($value);
        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function recursiveKsort(array &$value): void
    {
        ksort($value);
        foreach ($value as &$item) if (is_array($item)) $this->recursiveKsort($item);
    }

    private function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
