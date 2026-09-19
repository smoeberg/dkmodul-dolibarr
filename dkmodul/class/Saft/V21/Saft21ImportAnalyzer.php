<?php

require_once __DIR__.'/../../Accounting/Canonical/Decimal.php';

/**
 * Resolves imported SAF-T source accounts to local Dolibarr accounts.
 *
 * Resolution order:
 * 1. exact local account number match;
 * 2. reverse lookup through effective-dated DK standard-account mappings.
 *
 * Ambiguous and unresolved accounts block Apply.
 */
class DkSaft21ImportAnalyzer
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function analyze($entity, $importId, $mappingDate)
    {
        $entity = (int) $entity;
        $importId = (int) $importId;
        $mappingDate = trim((string) $mappingDate);

        if ($entity <= 0 || $importId <= 0) {
            throw new InvalidArgumentException('Entity and import id are required');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $mappingDate)) {
            throw new InvalidArgumentException('Mapping date must use YYYY-MM-DD');
        }

        $import = $this->loadImport($entity, $importId);
        $accounts = $this->loadAccounts($importId);
        $errors = array();
        $warnings = array();
        $resolved = array();

        foreach ($accounts as $account) {
            $source = (string) $account->source_account_id;
            $standard = trim((string) $account->standard_account_id);

            $exact = $this->findExactLocalAccount($entity, $source);
            if ($exact !== null) {
                $this->persistResolution($account->rowid, $exact, 'exact', null);
                $resolved[$source] = $exact;
                continue;
            }

            if ($standard === '') {
                $message = 'Imported account '.$source.' has no local match and no StandardAccountID';
                $this->persistResolution($account->rowid, null, 'unresolved', $message);
                $errors[] = $message;
                continue;
            }

            $candidates = $this->findStandardMappingCandidates(
                $entity,
                $standard,
                (string) $import->standard_account_version,
                $mappingDate
            );

            if (count($candidates) === 1) {
                $this->persistResolution($account->rowid, $candidates[0], 'standard_mapping', null);
                $resolved[$source] = $candidates[0];
                continue;
            }

            if (count($candidates) > 1) {
                $message = 'Imported account '.$source.' maps ambiguously via StandardAccountID '.$standard;
                $this->persistResolution($account->rowid, null, 'ambiguous', $message);
                $errors[] = $message;
                continue;
            }

            $message = 'No local mapping for imported account '.$source.' / StandardAccountID '.$standard;
            $this->persistResolution($account->rowid, null, 'unresolved', $message);
            $errors[] = $message;
        }

        $this->assertAllLineAccountsDeclared($importId, $accounts, $errors);
        $this->assertTransactionIntegrity($importId, $errors);

        if ($import->standard_account_version === null || trim((string) $import->standard_account_version) === '') {
            $warnings[] = 'Imported SAF-T file does not declare VersionOfStandardAccount';
        }

        $canApply = count($errors) === 0;
        $this->setBatchStatus($entity, $importId, $canApply ? 'ready' : 'blocked');

        return array(
            'canApply' => $canApply,
            'resolvedAccounts' => $resolved,
            'errors' => $errors,
            'warnings' => $warnings,
            'accountCount' => count($accounts),
        );
    }

    private function loadImport($entity, $importId)
    {
        $sql = 'SELECT rowid,standard_account_version,status';
        $sql .= ' FROM '.$this->db->prefix().'dk_saft_import';
        $sql .= ' WHERE rowid = '.$importId.' AND entity = '.$entity.' LIMIT 1';

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RuntimeException($this->db->lasterror());
        }

        $row = $this->db->fetch_object($resql);
        if (!$row) {
            throw new InvalidArgumentException('SAF-T import batch not found');
        }
        if ((string) $row->status === 'applied') {
            throw new InvalidArgumentException('Applied SAF-T imports cannot be re-analyzed for posting');
        }

        return $row;
    }

    private function loadAccounts($importId)
    {
        $sql = 'SELECT rowid,source_account_id,standard_account_id';
        $sql .= ' FROM '.$this->db->prefix().'dk_saft_import_account';
        $sql .= ' WHERE fk_import = '.((int) $importId);
        $sql .= ' ORDER BY rowid ASC';

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RuntimeException($this->db->lasterror());
        }

        $rows = array();
        while ($row = $this->db->fetch_object($resql)) {
            $rows[] = $row;
        }

        return $rows;
    }

    private function findExactLocalAccount($entity, $sourceAccount)
    {
        $sql = 'SELECT account_number FROM '.$this->db->prefix().'accounting_account';
        $sql .= ' WHERE entity = '.((int) $entity);
        $sql .= " AND account_number = '".$this->db->escape($sourceAccount)."'";
        $sql .= ' AND active = 1 LIMIT 2';

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RuntimeException($this->db->lasterror());
        }

        $values = array();
        while ($row = $this->db->fetch_object($resql)) {
            $values[] = (string) $row->account_number;
        }

        return count($values) === 1 ? $values[0] : null;
    }

    private function findStandardMappingCandidates($entity, $standardAccount, $standardVersion, $date)
    {
        $sql = 'SELECT DISTINCT aa.account_number';
        $sql .= ' FROM '.$this->db->prefix().'dk_account_mapping m';
        $sql .= ' INNER JOIN '.$this->db->prefix().'accounting_account aa';
        $sql .= ' ON aa.entity = m.entity AND aa.account_number = m.source_account';
        $sql .= ' WHERE m.entity = '.((int) $entity);
        $sql .= " AND m.standard_account = '".$this->db->escape($standardAccount)."'";
        if ($standardVersion !== '') {
            $sql .= " AND m.standard_version = '".$this->db->escape($standardVersion)."'";
        }
        $sql .= " AND m.valid_from <= '".$this->db->escape($date)."'";
        $sql .= " AND (m.valid_to IS NULL OR m.valid_to >= '".$this->db->escape($date)."')";
        $sql .= ' AND aa.active = 1';
        $sql .= ' ORDER BY aa.account_number ASC';

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RuntimeException($this->db->lasterror());
        }

        $result = array();
        while ($row = $this->db->fetch_object($resql)) {
            $result[] = (string) $row->account_number;
        }

        return $result;
    }

    private function persistResolution($rowId, $localAccount, $status, $note)
    {
        $sql = 'UPDATE '.$this->db->prefix().'dk_saft_import_account SET';
        $sql .= ' resolved_local_account = '.($localAccount === null ? 'NULL' : "'".$this->db->escape($localAccount)."'");
        $sql .= ", mapping_status = '".$this->db->escape($status)."'";
        $sql .= ', mapping_note = '.($note === null ? 'NULL' : "'".$this->db->escape($note)."'");
        $sql .= ' WHERE rowid = '.((int) $rowId);

        if (!$this->db->query($sql)) {
            throw new RuntimeException($this->db->lasterror());
        }
    }

    private function assertTransactionIntegrity($importId, array &$errors)
    {
        $sql = 'SELECT t.external_transaction_id,COUNT(l.rowid) AS line_count,';
        $sql .= ' COALESCE(SUM(l.debit),0) AS total_debit,COALESCE(SUM(l.credit),0) AS total_credit';
        $sql .= ' FROM '.$this->db->prefix().'dk_saft_import_transaction t';
        $sql .= ' LEFT JOIN '.$this->db->prefix().'dk_saft_import_line l';
        $sql .= ' ON l.fk_import_transaction = t.rowid';
        $sql .= ' WHERE t.fk_import = '.((int) $importId);
        $sql .= ' GROUP BY t.rowid,t.external_transaction_id';
        $sql .= ' ORDER BY t.rowid ASC';

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RuntimeException($this->db->lasterror());
        }

        while ($row = $this->db->fetch_object($resql)) {
            if ((int) $row->line_count < 2) {
                $errors[] = 'Transaction '.$row->external_transaction_id.' has fewer than two lines';
                continue;
            }

            $debit = DkCanonicalDecimal::normalize((string) $row->total_debit);
            $credit = DkCanonicalDecimal::normalize((string) $row->total_credit);

            if (!DkCanonicalDecimal::equals($debit, $credit)) {
                $errors[] = 'Transaction '.$row->external_transaction_id
                    .' is unbalanced (debit='.$debit.', credit='.$credit.')';
            }
        }
    }

    private function setBatchStatus($entity, $importId, $status)
    {
        $sql = 'UPDATE '.$this->db->prefix().'dk_saft_import';
        $sql .= " SET status = '".$this->db->escape($status)."'";
        $sql .= ' WHERE rowid = '.((int) $importId).' AND entity = '.((int) $entity);
        $sql .= " AND status <> 'applied'";

        if (!$this->db->query($sql)) {
            throw new RuntimeException($this->db->lasterror());
        }
    }

    private function assertAllLineAccountsDeclared($importId, array $accounts, array &$errors)
    {
        $declared = array();
        foreach ($accounts as $account) {
            $declared[(string) $account->source_account_id] = true;
        }

        $sql = 'SELECT DISTINCT l.source_account_id';
        $sql .= ' FROM '.$this->db->prefix().'dk_saft_import_line l';
        $sql .= ' INNER JOIN '.$this->db->prefix().'dk_saft_import_transaction t';
        $sql .= ' ON t.rowid = l.fk_import_transaction';
        $sql .= ' WHERE t.fk_import = '.((int) $importId);

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RuntimeException($this->db->lasterror());
        }

        while ($row = $this->db->fetch_object($resql)) {
            $account = (string) $row->source_account_id;
            if (!isset($declared[$account])) {
                $errors[] = 'Transaction line uses undeclared AccountID '.$account;
            }
        }
    }
}
