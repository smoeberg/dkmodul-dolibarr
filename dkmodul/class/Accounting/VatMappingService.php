<?php

class DkVatMappingService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function map($entity, $sourceTaxCode, $standardVersion, $standardTaxCode, $validFrom, $validTo, $userId)
    {
        $entity = (int) $entity;
        $sourceTaxCode = trim((string) $sourceTaxCode);
        $standardVersion = trim((string) $standardVersion);
        $standardTaxCode = trim((string) $standardTaxCode);
        $validFrom = trim((string) $validFrom);
        $validTo = $validTo === null || $validTo === '' ? null : trim((string) $validTo);
        $userId = (int) $userId;

        if ($entity <= 0 || $userId <= 0 || $sourceTaxCode === '' || $standardTaxCode === '') {
            throw new InvalidArgumentException('Invalid VAT mapping');
        }

        $this->assertDate($validFrom);
        if ($validTo !== null) {
            $this->assertDate($validTo);
            if ($validTo < $validFrom) {
                throw new InvalidArgumentException('VAT mapping valid_to cannot be before valid_from');
            }
        }

        $this->assertTargetExists($standardVersion, $standardTaxCode);
        $this->assertNoOverlap($entity, $sourceTaxCode, $validFrom, $validTo);

        $sql = 'INSERT INTO '.$this->db->prefix().'dk_vat_mapping';
        $sql .= ' (entity,source_tax_code,standard_version,standard_tax_code,valid_from,valid_to,fk_user_author,date_creation)';
        $sql .= ' VALUES ('.$entity;
        $sql .= ",'".$this->db->escape($sourceTaxCode)."'";
        $sql .= ",'".$this->db->escape($standardVersion)."'";
        $sql .= ",'".$this->db->escape($standardTaxCode)."'";
        $sql .= ",'".$this->db->escape($validFrom)."'";
        $sql .= ','.($validTo === null ? 'NULL' : "'".$this->db->escape($validTo)."'");
        $sql .= ','.$userId.',NOW())';

        if (!$this->db->query($sql)) {
            throw new RuntimeException('Unable to save VAT mapping: '.$this->db->lasterror());
        }

        return 1;
    }

    public function resolve($entity, $sourceTaxCode, $date)
    {
        $this->assertDate($date);

        $sql = 'SELECT m.standard_version,m.standard_tax_code,m.valid_from,m.valid_to,v.label,v.tax_percentage,v.country_code';
        $sql .= ' FROM '.$this->db->prefix().'dk_vat_mapping m';
        $sql .= ' INNER JOIN '.$this->db->prefix().'dk_standard_vat_code v';
        $sql .= ' ON v.standard_version = m.standard_version AND v.tax_code = m.standard_tax_code';
        $sql .= ' WHERE m.entity = '.((int) $entity);
        $sql .= " AND m.source_tax_code = '".$this->db->escape((string) $sourceTaxCode)."'";
        $sql .= " AND m.valid_from <= '".$this->db->escape($date)."'";
        $sql .= " AND (m.valid_to IS NULL OR m.valid_to >= '".$this->db->escape($date)."')";
        $sql .= ' ORDER BY m.valid_from DESC LIMIT 1';

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RuntimeException('Unable to resolve VAT mapping: '.$this->db->lasterror());
        }

        $row = $this->db->fetch_object($resql);
        if (!$row) {
            return null;
        }

        return array(
            'standardVersion' => (string) $row->standard_version,
            'standardTaxCode' => (string) $row->standard_tax_code,
            'effectiveDate' => (string) $row->valid_from,
            'expirationDate' => $row->valid_to !== null ? (string) $row->valid_to : null,
            'description' => (string) $row->label,
            'taxPercentage' => $row->tax_percentage !== null ? (string) $row->tax_percentage : null,
            'countryCode' => (string) $row->country_code,
        );
    }

    private function assertTargetExists($version, $taxCode)
    {
        $sql = 'SELECT COUNT(*) AS nb FROM '.$this->db->prefix().'dk_standard_vat_code';
        $sql .= " WHERE standard_version = '".$this->db->escape((string) $version)."'";
        $sql .= " AND tax_code = '".$this->db->escape((string) $taxCode)."'";

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RuntimeException('Unable to validate standard VAT code: '.$this->db->lasterror());
        }

        $row = $this->db->fetch_object($resql);
        if (!$row || (int) $row->nb !== 1) {
            throw new InvalidArgumentException('Target VAT code does not exist in selected standard VAT list');
        }
    }

    private function assertNoOverlap($entity, $sourceCode, $from, $to)
    {
        $sql = 'SELECT COUNT(*) AS nb FROM '.$this->db->prefix().'dk_vat_mapping';
        $sql .= ' WHERE entity = '.((int) $entity);
        $sql .= " AND source_tax_code = '".$this->db->escape((string) $sourceCode)."'";
        $sql .= " AND (valid_to IS NULL OR valid_to >= '".$this->db->escape($from)."')";
        if ($to !== null) {
            $sql .= " AND valid_from <= '".$this->db->escape($to)."'";
        }

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RuntimeException('Unable to validate VAT mapping period: '.$this->db->lasterror());
        }

        $row = $this->db->fetch_object($resql);
        if ($row && (int) $row->nb > 0) {
            throw new InvalidArgumentException('VAT mapping periods may not overlap');
        }
    }

    private function assertDate($value)
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value)) {
            throw new InvalidArgumentException('Date must use YYYY-MM-DD');
        }
    }
}
