<?php

/**
 * Imports Erhvervsstyrelsen's public Danish VAT-code list.
 *
 * The current public JSON contains both the established "momskode" family
 * (for example S1) and a newer "momskode NY" family (for example S01).
 * SAF-T 2.1 currently uses the established code family as StandardTaxCode,
 * but both are retained so a later switch can be versioned instead of
 * rewriting historical mappings.
 */
class DkStandardVatCodeImporter
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db;
    }

    public function importJson($json, $standardVersion = '20260101')
    {
        if ($this->db === null) {
            throw new RuntimeException('A database connection is required to import VAT codes');
        }

        $parsed = $this->parseJson($json, $standardVersion);
        $sourceHash = hash('sha256', (string) $json);

        $this->db->begin();

        try {
            $delete = 'DELETE FROM '.$this->db->prefix().'dk_standard_vat_code';
            $delete .= " WHERE standard_version = '".$this->db->escape($standardVersion)."'";

            if (!$this->db->query($delete)) {
                throw new RuntimeException($this->db->lasterror());
            }

            foreach ($parsed['codes'] as $code) {
                $sql = 'INSERT INTO '.$this->db->prefix().'dk_standard_vat_code';
                $sql .= ' (standard_version,valid_from,tax_code,new_tax_code,legacy_label_code,new_label_code,';
                $sql .= 'tax_group,tax_type,label,tax_percentage,country_code,reporting_box,deduction_right,guidance,source_hash,date_imported)';
                $sql .= " VALUES ('".$this->db->escape($standardVersion)."'";
                $sql .= ",'".$this->db->escape($parsed['validFrom'])."'";
                $sql .= ",'".$this->db->escape($code['legacyCode'])."'";
                $sql .= $this->nullable($code['newCode']);
                $sql .= $this->nullable($code['legacyLabelCode']);
                $sql .= $this->nullable($code['newLabelCode']);
                $sql .= $this->nullable($code['group']);
                $sql .= $this->nullable($code['type']);
                $sql .= ",'".$this->db->escape($code['label'])."'";
                $sql .= $code['taxPercentage'] === null ? ',NULL' : ','.((float) $code['taxPercentage']);
                $sql .= ",'DK'";
                $sql .= $this->nullable($code['reportingBox']);
                $sql .= $this->nullable($code['deductionRight']);
                $sql .= $this->nullable($code['guidance']);
                $sql .= ",'".$this->db->escape($sourceHash)."'";
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
            'version' => $standardVersion,
            'validFrom' => $parsed['validFrom'],
            'sourceHash' => $sourceHash,
            'codeCount' => count($parsed['codes']),
        );
    }

    public function parseJson($json, $standardVersion = '20260101')
    {
        $data = json_decode((string) $json, true);

        if (!is_array($data)) {
            throw new InvalidArgumentException('Invalid standard VAT JSON');
        }

        $fileInfo = isset($data['File info']) && is_array($data['File info'])
            ? $data['File info']
            : array();

        if (($fileInfo['Document name'] ?? '') !== 'Momskoder-Bruttoliste') {
            throw new InvalidArgumentException('Unexpected VAT document type');
        }

        $validFrom = trim((string) ($fileInfo['Valid from date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $validFrom)) {
            throw new InvalidArgumentException('VAT JSON must contain a valid File info / Valid from date');
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
                if ($legacyCode === '') {
                    continue;
                }

                if (isset($codes[$legacyCode])) {
                    throw new InvalidArgumentException('Duplicate standard VAT code '.$legacyCode);
                }

                $label = trim((string) ($item['overskrift'] ?? ''));
                if ($label === '') {
                    throw new InvalidArgumentException('VAT code '.$legacyCode.' has no heading/label');
                }

                $codes[$legacyCode] = array(
                    'legacyCode' => $legacyCode,
                    'newCode' => $this->emptyToNull($item['momskode NY'] ?? null),
                    'legacyLabelCode' => $this->emptyToNull($item['momskode betegnelse'] ?? null),
                    'newLabelCode' => $this->emptyToNull($item['momskode betegnelse NY'] ?? null),
                    'group' => $groupName !== '' ? $groupName : null,
                    'type' => $this->emptyToNull($item['type'] ?? null),
                    'label' => $label,
                    'taxPercentage' => $this->parsePercentage($item['momssats'] ?? null),
                    'reportingBox' => $this->emptyToNull($item['momsangivelse'] ?? null),
                    'deductionRight' => $this->emptyToNull($item['fradragsret'] ?? null),
                    'guidance' => $this->emptyToNull($item['vejledning'] ?? null),
                );
            }
        }

        if (!$codes) {
            throw new InvalidArgumentException('Standard VAT list contains no VAT codes');
        }

        ksort($codes, SORT_NATURAL);

        return array(
            'version' => (string) $standardVersion,
            'validFrom' => $validFrom,
            'codes' => array_values($codes),
        );
    }

    private function parsePercentage($value)
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (!preg_match('/^(-?[0-9]+(?:[.,][0-9]+)?)%$/', $value, $match)) {
            // Public list also contains symbolic rates such as x%.
            return null;
        }

        return str_replace(',', '.', $match[1]);
    }

    private function emptyToNull($value)
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function nullable($value)
    {
        if ($value === null || $value === '') {
            return ',NULL';
        }

        return ",'".$this->db->escape((string) $value)."'";
    }
}
