<?php

/**
 * Imports Erhvervsstyrelsen's official Danish VAT-code gross list.
 *
 * The upstream JSON contains grouped VAT codes and exposes both the legacy
 * "momskode" (for example S1) and the newer "momskode NY" (for example S01).
 * SAF-T 2.1 currently uses the public standard code family represented by
 * tax_code; the next code family is retained for future migrations.
 */
class DkStandardVatImporter
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db;
    }

    public function parseJson($json, $releaseVersion)
    {
        $json = (string) $json;
        $data = json_decode($json, true);

        if (!is_array($data)) {
            throw new InvalidArgumentException('Invalid standard VAT JSON');
        }

        $releaseVersion = trim((string) $releaseVersion);
        if (!preg_match('/^\d{8}$/', $releaseVersion)) {
            throw new InvalidArgumentException('VAT release version must use YYYYMMDD');
        }

        $fileInfo = isset($data['File info']) && is_array($data['File info'])
            ? $data['File info']
            : array();
        $validFrom = isset($fileInfo['Valid from date'])
            ? trim((string) $fileInfo['Valid from date'])
            : '';

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $validFrom)) {
            throw new InvalidArgumentException('VAT JSON must contain File info / Valid from date');
        }

        $groups = $data['momskoder - bruttoliste'] ?? null;
        if (!is_array($groups)) {
            throw new InvalidArgumentException('VAT JSON must contain momskoder - bruttoliste');
        }

        $codes = array();

        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }

            $groupName = trim((string) ($group['momsgruppe'] ?? ''));
            $items = $group['momskoder'] ?? array();

            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $legacyCode = trim((string) ($item['momskode'] ?? ''));
                $nextCode = trim((string) ($item['momskode NY'] ?? ''));

                if ($legacyCode === '' && $nextCode === '') {
                    continue;
                }

                // The legacy code is the SAF-T 2.1 mapping key where present.
                $code = $legacyCode !== '' ? $legacyCode : $nextCode;
                $percentage = $this->parsePercentage($item['momssats'] ?? null);

                if (isset($codes[$code])) {
                    throw new InvalidArgumentException('Duplicate VAT code in official list: '.$code);
                }

                $codes[$code] = array(
                    'releaseVersion' => $releaseVersion,
                    'validFrom' => $validFrom,
                    'taxCode' => $code,
                    'legacyTaxCode' => $legacyCode !== '' ? $legacyCode : null,
                    'nextTaxCode' => $nextCode !== '' ? $nextCode : null,
                    'designation' => trim((string) ($item['momskode betegnelse'] ?? '')),
                    'nextDesignation' => trim((string) ($item['momskode betegnelse NY'] ?? '')),
                    'groupName' => $groupName,
                    'label' => trim((string) ($item['overskrift'] ?? '')),
                    'taxPercentage' => $percentage,
                    'taxType' => trim((string) ($item['type'] ?? '')),
                    'vatReturnBox' => trim((string) ($item['momsangivelse'] ?? '')),
                    'deductionRight' => trim((string) ($item['fradragsret'] ?? '')),
                    'guidance' => trim((string) ($item['vejledning'] ?? '')),
                    'countryCode' => 'DK',
                );
            }
        }

        if (!$codes) {
            throw new InvalidArgumentException('Official VAT list contains no VAT codes');
        }

        return array(
            'releaseVersion' => $releaseVersion,
            'validFrom' => $validFrom,
            'sourceHash' => hash('sha256', $json),
            'codes' => array_values($codes),
        );
    }

    public function importJson($json, $releaseVersion)
    {
        if ($this->db === null) {
            throw new RuntimeException('A database connection is required to import VAT codes');
        }

        $parsed = $this->parseJson($json, $releaseVersion);
        $this->db->begin();

        try {
            $delete = 'DELETE FROM '.$this->db->prefix().'dk_standard_vat_code';
            $delete .= " WHERE standard_version = '".$this->db->escape($parsed['releaseVersion'])."'";

            if (!$this->db->query($delete)) {
                throw new RuntimeException($this->db->lasterror());
            }

            foreach ($parsed['codes'] as $code) {
                $sql = 'INSERT INTO '.$this->db->prefix().'dk_standard_vat_code';
                $sql .= ' (standard_version,valid_from,tax_code,legacy_tax_code,next_tax_code,designation,next_designation,group_name,label,tax_percentage,tax_type,vat_return_box,deduction_right,guidance,country_code,source_hash,date_imported)';
                $sql .= " VALUES ('".$this->db->escape($parsed['releaseVersion'])."'";
                $sql .= ",'".$this->db->escape($parsed['validFrom'])."'";
                $sql .= ",'".$this->db->escape($code['taxCode'])."'";
                $sql .= ','.$this->sqlNullable($code['legacyTaxCode']);
                $sql .= ','.$this->sqlNullable($code['nextTaxCode']);
                $sql .= ','.$this->sqlNullable($code['designation']);
                $sql .= ','.$this->sqlNullable($code['nextDesignation']);
                $sql .= ','.$this->sqlNullable($code['groupName']);
                $sql .= ",'".$this->db->escape($code['label'])."'";
                $sql .= ','.($code['taxPercentage'] === null ? 'NULL' : "'".$this->db->escape($code['taxPercentage'])."'");
                $sql .= ','.$this->sqlNullable($code['taxType']);
                $sql .= ','.$this->sqlNullable($code['vatReturnBox']);
                $sql .= ','.$this->sqlNullable($code['deductionRight']);
                $sql .= ','.$this->sqlNullable($code['guidance']);
                $sql .= ",'DK'";
                $sql .= ",'".$this->db->escape($parsed['sourceHash'])."'";
                $sql .= ',NOW())';

                if (!$this->db->query($sql)) {
                    throw new RuntimeException($this->db->lasterror());
                }
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollback();
            throw new RuntimeException('Unable to import Danish standard VAT codes: '.$e->getMessage(), 0, $e);
        }

        return array(
            'version' => $parsed['releaseVersion'],
            'validFrom' => $parsed['validFrom'],
            'sourceHash' => $parsed['sourceHash'],
            'codeCount' => count($parsed['codes']),
        );
    }

    private function parsePercentage($value)
    {
        $value = trim((string) $value);

        if ($value === '' || strtolower($value) === 'x%') {
            return null;
        }

        $value = str_replace(array('%', ',', ' '), array('', '.', ''), $value);

        if (!is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 6, '.', '');
    }

    private function sqlNullable($value)
    {
        if ($value === null || trim((string) $value) === '') {
            return 'NULL';
        }

        return "'".$this->db->escape((string) $value)."'";
    }
}
