<?php

require_once dirname(__DIR__).'/Audit/AuditLedger.php';

/**
 * Content-addressed archive for evidence linked to a bookkeeping row.
 * Records and stored bytes are immutable in the initial certified profile.
 */
final class DkDocumentArchiveService
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

    public function archive(
        int $entity,
        int $bookkeepingRowId,
        string $sourceType,
        int $sourceId,
        string $sourcePath,
        string $originalName,
        string $fiscalYearEnd,
        int $actorId,
        ?string $mimeType = null
    ): array {
        if ($entity <= 0 || $bookkeepingRowId <= 0 || $actorId <= 0) {
            throw new InvalidArgumentException('Entity, bookkeeping row and actor are required');
        }
        if (trim($sourceType) === '' || trim($originalName) === '' || $this->storageRoot === '') {
            throw new InvalidArgumentException('Document source type, original name and storage root are required');
        }
        if (!is_file($sourcePath) || !is_readable($sourcePath)) {
            throw new InvalidArgumentException('Document source must be a readable file');
        }
        $yearEnd = DateTimeImmutable::createFromFormat('!Y-m-d', $fiscalYearEnd);
        if (!$yearEnd || $yearEnd->format('Y-m-d') !== $fiscalYearEnd) {
            throw new InvalidArgumentException('Fiscal year end must use YYYY-MM-DD');
        }
        $this->assertBookkeepingRow($entity, $bookkeepingRowId);

        $hash = hash_file('sha256', $sourcePath);
        $size = filesize($sourcePath);
        if ($hash === false || $size === false) {
            throw new RuntimeException('Unable to fingerprint document');
        }
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $extension = preg_match('/^[a-z0-9]{1,10}$/', $extension) ? '.'.$extension : '';
        $storageKey = $entity.'/'.$yearEnd->format('Y').'/'.substr($hash, 0, 2).'/'.$hash.$extension;
        $target = $this->storageRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $storageKey);
        $createdFile = $this->storeImmutable($sourcePath, $target, $hash);
        $uuid = $this->uuidV4();
        $retainUntil = $yearEnd->modify('+5 years')->format('Y-m-d');
        $mimeType = trim((string) $mimeType);
        if ($mimeType === '') {
            $mimeType = function_exists('mime_content_type') ? (string) mime_content_type($sourcePath) : 'application/octet-stream';
        }

        $this->db->begin();
        try {
            $sql = 'INSERT INTO '.$this->db->prefix().'dk_document_archive';
            $sql .= ' (entity,document_uuid,bookkeeping_rowid,source_type,source_id,original_name,mime_type,storage_key,content_hash,byte_size,fiscal_year_end,retain_until,fk_user_author,created_at) VALUES (';
            $sql .= $entity.",'".$this->db->escape($uuid)."',".$bookkeepingRowId;
            $sql .= ",'".$this->db->escape(trim($sourceType))."',".$sourceId;
            $sql .= ",'".$this->db->escape($originalName)."','".$this->db->escape($mimeType)."'";
            $sql .= ",'".$this->db->escape($storageKey)."','".$hash."',".((int) $size);
            $sql .= ",'".$fiscalYearEnd."','".$retainUntil."',".$actorId.',UTC_TIMESTAMP())';
            if (!$this->db->query($sql)) {
                throw new RuntimeException('Unable to register archived document: '.$this->db->lasterror());
            }
            $rowId = (int) $this->db->last_insert_id($this->db->prefix().'dk_document_archive');
            $this->audit->append($entity, 'document.archived', 'dk_document_archive', $rowId, $actorId, array(
                'bookkeepingRowId' => $bookkeepingRowId,
                'contentHash' => $hash,
                'retainUntil' => $retainUntil,
            ));
            if ($this->db->commit() <= 0) {
                throw new RuntimeException('Unable to commit archived document');
            }
        } catch (Throwable $e) {
            $this->db->rollback();
            if ($createdFile && is_file($target)) {
                @unlink($target);
            }
            throw $e;
        }

        return array('rowid' => $rowId, 'documentUuid' => $uuid, 'contentHash' => $hash, 'storageKey' => $storageKey, 'retainUntil' => $retainUntil);
    }

    public function verify(int $entity, int $rowId): bool
    {
        $sql = 'SELECT storage_key,content_hash,byte_size FROM '.$this->db->prefix().'dk_document_archive';
        $sql .= ' WHERE entity='.$entity.' AND rowid='.$rowId;
        $resql = $this->db->query($sql);
        $row = $resql ? $this->db->fetch_object($resql) : false;
        if (!$row) {
            return false;
        }
        $path = $this->storageRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $row->storage_key);
        return is_file($path)
            && filesize($path) === (int) $row->byte_size
            && hash_equals((string) $row->content_hash, (string) hash_file('sha256', $path));
    }

    private function assertBookkeepingRow(int $entity, int $rowId): void
    {
        $sql = 'SELECT rowid FROM '.$this->db->prefix().'accounting_bookkeeping WHERE entity='.$entity.' AND rowid='.$rowId;
        $resql = $this->db->query($sql);
        if (!$resql || !$this->db->fetch_object($resql)) {
            throw new InvalidArgumentException('Bookkeeping row does not exist in entity');
        }
    }

    private function storeImmutable(string $source, string $target, string $expectedHash): bool
    {
        if (is_file($target)) {
            if (!hash_equals($expectedHash, (string) hash_file('sha256', $target))) {
                throw new RuntimeException('Archived content hash collision or corruption');
            }
            return false;
        }
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create document archive directory');
        }
        $temporary = $target.'.tmp.'.bin2hex(random_bytes(6));
        if (!copy($source, $temporary) || !rename($temporary, $target)) {
            @unlink($temporary);
            throw new RuntimeException('Unable to store archived document');
        }
        chmod($target, 0440);
        return true;
    }

    private function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
