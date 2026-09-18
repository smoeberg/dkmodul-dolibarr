<?php

/**
 * Imports the official Danish standard chart JSON into a versioned local registry.
 *
 * The importer deliberately consumes only stable semantic fields and ignores
 * unknown fields, so ERST can add metadata without breaking ingestion.
 */
class DkStandardChartImporter
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function importJson($json)
    {
        $json = (string) $json;
        $data = json_decode($json, true);

        if (!is_array($data)) {
            throw new InvalidArgumentException('Invalid standard chart JSON');
        }

        $fileInfo = isset($data['File info']) && is_array($data['File info']) ? $data['File info'] : array();
        $validFrom = isset($fileInfo['Valid from date']) ? (string) $fileInfo['Valid from date'] : '';

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $validFrom)) {
            throw new InvalidArgumentException('Standard chart JSON must contain a valid File info / Valid from date');
        }

        if (!isset($data['Kontoplan']) || !is_array($data['Kontoplan'])) {
            throw new InvalidArgumentException('Standard chart JSON must contain Kontoplan');
        }

        $version = $validFrom;
        $sourceHash = hash('sha256', $json);
        $accounts = array();
        $this->collectAccounts($data['Kontoplan'], $accounts);

        if (!$accounts) {
            throw new InvalidArgumentException('Standard chart contains no accounts');
        }

        $this->db->begin();

        try {
            $delete = "DELETE FROM ".$this->db->prefix()."dk_standard_account";
            $delete .= " WHERE standard_version = '".$this->db->escape($version)."'";
            if (!$this->db->query($delete)) {
                throw new RuntimeException($this->db->lasterror());
            }

            foreach ($accounts as $account) {
                $sql = "INSERT INTO ".$this->db->prefix()."dk_standard_account";
                $sql .= " (standard_version,valid_from,account_code,account_type,label,source_hash,date_imported)";
                $sql .= " VALUES ('".$this->db->escape($version)."'";
                $sql .= ",'".$this->db->escape($validFrom)."'";
                $sql .= ",'".$this->db->escape($account['code'])."'";
                $sql .= ",".($account['type'] === null ? "NULL" : "'".$this->db->escape($account['type'])."'");
                $sql .= ",'".$this->db->escape($account['label'])."'";
                $sql .= ",'".$this->db->escape($sourceHash)."'";
                $sql .= ",NOW())";

                if (!$this->db->query($sql)) {
                    throw new RuntimeException($this->db->lasterror());
                }
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollback();
            throw new RuntimeException('Unable to import Danish standard chart: '.$e->getMessage(), 0, $e);
        }

        return array(
            'version' => $version,
            'validFrom' => $validFrom,
            'sourceHash' => $sourceHash,
            'accountCount' => count($accounts),
        );
    }

    public function parseJson($json)
    {
        $data = json_decode((string) $json, true);
        if (!is_array($data) || !isset($data['Kontoplan']) || !is_array($data['Kontoplan'])) {
            throw new InvalidArgumentException('Invalid standard chart JSON');
        }

        $accounts = array();
        $this->collectAccounts($data['Kontoplan'], $accounts);
        return array_values($accounts);
    }

    private function collectAccounts(array $nodes, array &$accounts)
    {
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }

            if (isset($node['kontonummer']) && isset($node['navn'])) {
                $code = (string) $node['kontonummer'];
                $accounts[$code] = array(
                    'code' => $code,
                    'type' => isset($node['kontotype']) ? (string) $node['kontotype'] : null,
                    'label' => trim((string) $node['navn']),
                );
            }

            if (isset($node['konti']) && is_array($node['konti'])) {
                $this->collectAccounts($node['konti'], $accounts);
            }
        }
    }
}
