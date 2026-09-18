<?php

class DkAccountMappingService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function map($entity, $sourceAccount, $standardVersion, $standardAccount, $validFrom, $validTo, $userId)
    {
        $entity = (int) $entity;
        $sourceAccount = trim((string) $sourceAccount);
        $standardVersion = trim((string) $standardVersion);
        $standardAccount = trim((string) $standardAccount);
        $validFrom = trim((string) $validFrom);
        $validTo = $validTo === null || $validTo === '' ? null : trim((string) $validTo);
        $userId = (int) $userId;

        if ($entity <= 0 || $userId <= 0 || $sourceAccount === '' || $standardAccount === '') {
            throw new InvalidArgumentException('Invalid account mapping');
        }
        $this->assertDate($validFrom);
        if ($validTo !== null) {
            $this->assertDate($validTo);
            if ($validTo < $validFrom) {
                throw new InvalidArgumentException('Mapping valid_to cannot be before valid_from');
            }
        }

        $this->assertTargetExists($standardVersion, $standardAccount);
        $this->assertNoOverlap($entity, $sourceAccount, $validFrom, $validTo);

        $sql = "INSERT INTO ".$this->db->prefix()."dk_account_mapping";
        $sql .= " (entity,source_account,standard_version,standard_account,valid_from,valid_to,fk_user_author,date_creation)";
        $sql .= " VALUES (".$entity;
        $sql .= ",'".$this->db->escape($sourceAccount)."'";
        $sql .= ",'".$this->db->escape($standardVersion)."'";
        $sql .= ",'".$this->db->escape($standardAccount)."'";
        $sql .= ",'".$this->db->escape($validFrom)."'";
        $sql .= ",".($validTo === null ? "NULL" : "'".$this->db->escape($validTo)."'");
        $sql .= ",".$userId.",NOW())";

        if (!$this->db->query($sql)) {
            throw new RuntimeException('Unable to save account mapping: '.$this->db->lasterror());
        }

        return 1;
    }

    public function resolve($entity, $sourceAccount, $date)
    {
        $this->assertDate($date);

        $sql = "SELECT standard_version,standard_account";
        $sql .= " FROM ".$this->db->prefix()."dk_account_mapping";
        $sql .= " WHERE entity = ".((int) $entity);
        $sql .= " AND source_account = '".$this->db->escape((string) $sourceAccount)."'";
        $sql .= " AND valid_from <= '".$this->db->escape($date)."'";
        $sql .= " AND (valid_to IS NULL OR valid_to >= '".$this->db->escape($date)."')";
        $sql .= " ORDER BY valid_from DESC LIMIT 1";

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RuntimeException('Unable to resolve account mapping: '.$this->db->lasterror());
        }

        $row = $this->db->fetch_object($resql);
        if (!$row) {
            return null;
        }

        return array(
            'standardVersion' => (string) $row->standard_version,
            'standardAccount' => (string) $row->standard_account,
        );
    }

    private function assertTargetExists($version, $account)
    {
        $sql = "SELECT COUNT(*) AS nb FROM ".$this->db->prefix()."dk_standard_account";
        $sql .= " WHERE standard_version = '".$this->db->escape($version)."'";
        $sql .= " AND account_code = '".$this->db->escape($account)."'";

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RuntimeException('Unable to validate standard account: '.$this->db->lasterror());
        }

        $row = $this->db->fetch_object($resql);
        if (!$row || (int) $row->nb !== 1) {
            throw new InvalidArgumentException('Target account does not exist in the selected standard chart version');
        }
    }

    private function assertNoOverlap($entity, $source, $from, $to)
    {
        $sql = "SELECT COUNT(*) AS nb FROM ".$this->db->prefix()."dk_account_mapping";
        $sql .= " WHERE entity = ".((int) $entity);
        $sql .= " AND source_account = '".$this->db->escape($source)."'";
        $sql .= " AND (valid_to IS NULL OR valid_to >= '".$this->db->escape($from)."')";
        if ($to !== null) {
            $sql .= " AND valid_from <= '".$this->db->escape($to)."'";
        }

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RuntimeException('Unable to validate account mapping period: '.$this->db->lasterror());
        }

        $row = $this->db->fetch_object($resql);
        if ($row && (int) $row->nb > 0) {
            throw new InvalidArgumentException('Account mapping periods may not overlap');
        }
    }

    private function assertDate($value)
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value)) {
            throw new InvalidArgumentException('Date must use YYYY-MM-DD');
        }
    }
}
