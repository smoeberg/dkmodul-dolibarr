<?php

require_once __DIR__.'/../../Audit/AuditLedger.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

/**
 * Applies a fully analyzed SAF-T staging batch to Dolibarr bookkeeping.
 *
 * Preconditions:
 * - batch status = staged
 * - every imported account has a resolved local account
 * - caller runs inside the matching Dolibarr entity
 *
 * The service opens one outer DoliDB transaction. BookKeeping::createStd()
 * uses nested transactions, so individual line commits do not reach MariaDB
 * until the outer transaction is committed.
 */
class DkSaft21ImportApplyService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function apply($entity, $importId, $user)
    {
        global $conf;

        $entity = (int) $entity;
        $importId = (int) $importId;

        if ($entity <= 0 || $importId <= 0 || !is_object($user) || empty($user->id)) {
            throw new InvalidArgumentException('Entity, import id and importing user are required');
        }

        if ((int) $conf->entity !== $entity) {
            throw new InvalidArgumentException('SAF-T import must be applied in the active Dolibarr entity');
        }

        require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';

        $this->db->begin('DK SAF-T import apply');

        try {
            $batch = $this->loadBatchForUpdate($entity, $importId);
            if ((string) $batch->status !== 'ready') {
                throw new InvalidArgumentException('Only analyzed and ready SAF-T imports can be applied');
            }

            $this->assertMappingsResolved($importId);
            $this->lockBookkeepingSequence($entity);

            $transactions = $this->loadTransactions($importId);
            if (!$transactions) {
                throw new InvalidArgumentException('SAF-T import batch contains no staged transactions');
            }

            $appliedLines = 0;
            $transactionSequence = 0;

            foreach ($transactions as $transaction) {
                $transactionSequence++;

                $allocator = new BookKeeping($this->db);
                $pieceNum = (int) $allocator->getNextNumMvt();
                if ($pieceNum <= 0) {
                    throw new RuntimeException('Unable to allocate Dolibarr bookkeeping piece number');
                }

                $localRef = $this->buildLocalRef($importId, $transactionSequence);
                $lines = $this->loadTransactionLines((int) $transaction->rowid);

                if (count($lines) < 2) {
                    throw new RuntimeException(
                        'Staged transaction '.$transaction->external_transaction_id.' has fewer than two lines'
                    );
                }

                foreach ($lines as $line) {
                    $account = $this->loadResolvedAccount($importId, (string) $line->source_account_id);
                    $bookkeeping = new BookKeeping($this->db);

                    $bookkeeping->doc_date = $this->db->jdate($transaction->transaction_date);
                    $bookkeeping->doc_type = 'saft_import';
                    $bookkeeping->doc_ref = (string) $transaction->external_transaction_id;
                    $bookkeeping->fk_doc = $importId;
                    $bookkeeping->fk_docdet = (int) $line->rowid;
                    $bookkeeping->thirdparty_code = '';
                    $bookkeeping->subledger_account = '';
                    $bookkeeping->subledger_label = '';
                    $bookkeeping->numero_compte = (string) $account->resolved_local_account;
                    $bookkeeping->label_compte = $this->localAccountLabel(
                        $entity,
                        (string) $account->resolved_local_account,
                        (string) $account->description
                    );
                    $bookkeeping->label_operation = trim((string) $line->description) !== ''
                        ? (string) $line->description
                        : 'SAF-T import '.$transaction->external_transaction_id;
                    $bookkeeping->debit = (float) $line->debit;
                    $bookkeeping->credit = (float) $line->credit;
                    $bookkeeping->montant = ((float) $line->debit) > 0
                        ? (float) $line->debit
                        : -1 * (float) $line->credit;
                    $bookkeeping->sens = ((float) $line->debit) > 0 ? 'D' : 'C';
                    $bookkeeping->import_key = $this->buildImportKey($importId);
                    $bookkeeping->code_journal = $this->journalCode((string) $transaction->journal_id);
                    $bookkeeping->journal_label = trim((string) $transaction->journal_description) !== ''
                        ? (string) $transaction->journal_description
                        : (string) $transaction->journal_id;
                    $bookkeeping->piece_num = $pieceNum;
                    $bookkeeping->ref = $localRef;
                    $bookkeeping->entity = $entity;

                    $rowId = $bookkeeping->createStd($user, 0, '');
                    if ($rowId <= 0) {
                        $errors = !empty($bookkeeping->errors)
                            ? implode('; ', $bookkeeping->errors)
                            : (string) $bookkeeping->error;
                        throw new RuntimeException(
                            'Dolibarr rejected imported bookkeeping line '.$line->external_record_id
                            .($errors !== '' ? ': '.$errors : '')
                        );
                    }

                    $this->markLineApplied((int) $line->rowid, (int) $rowId);
                    $appliedLines++;
                }

                $this->markTransactionApplied((int) $transaction->rowid, $pieceNum, $localRef);
            }

            // Imported entries become immutable before the outer transaction commits.
            $sql = 'UPDATE '.$this->db->prefix().'accounting_bookkeeping';
            $sql .= ' SET date_validated = NOW()';
            $sql .= " WHERE doc_type = 'saft_import'";
            $sql .= ' AND fk_doc = '.$importId;
            $sql .= ' AND entity = '.$entity;
            $sql .= ' AND date_validated IS NULL';

            if (!$this->db->query($sql)) {
                throw new RuntimeException('Unable to lock imported bookkeeping entries: '.$this->db->lasterror());
            }

            $sql = 'UPDATE '.$this->db->prefix().'dk_saft_import';
            $sql .= " SET status = 'applied', date_applied = NOW()";
            $sql .= ' WHERE rowid = '.$importId.' AND entity = '.$entity." AND status = 'ready'";

            if (!$this->db->query($sql)) {
                throw new RuntimeException('Unable to mark SAF-T import as applied: '.$this->db->lasterror());
            }

            $audit = new DkAuditLedger($this->db);
            $audit->append(
                $entity,
                'saft.import.applied',
                'dk_saft_import',
                $importId,
                (int) $user->id,
                array(
                    'source_hash' => (string) $batch->source_hash,
                    'transactions' => count($transactions),
                    'lines' => $appliedLines,
                )
            );

            $this->db->commit('DK SAF-T import apply');

            return array(
                'status' => 'applied',
                'transactionCount' => count($transactions),
                'lineCount' => $appliedLines,
            );
        } catch (Throwable $e) {
            $this->db->rollback('DK SAF-T import apply failed');
            throw new RuntimeException('SAF-T Apply failed: '.$e->getMessage(), 0, $e);
        }
    }

    private function loadBatchForUpdate($entity, $importId)
    {
        $sql = 'SELECT rowid,status,source_hash';
        $sql .= ' FROM '.$this->db->prefix().'dk_saft_import';
        $sql .= ' WHERE rowid = '.$importId.' AND entity = '.$entity;
        $sql .= ' FOR UPDATE';

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RuntimeException($this->db->lasterror());
        }

        $row = $this->db->fetch_object($resql);
        if (!$row) {
            throw new InvalidArgumentException('SAF-T import batch not found');
        }

        return $row;
    }

    private function assertMappingsResolved($importId)
    {
        $sql = 'SELECT COUNT(*) AS nb FROM '.$this->db->prefix().'dk_saft_import_account';
        $sql .= ' WHERE fk_import = '.((int) $importId);
        $sql .= " AND (resolved_local_account IS NULL OR mapping_status NOT IN ('exact','standard_mapping'))";

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RuntimeException($this->db->lasterror());
        }

        $row = $this->db->fetch_object($resql);
        if (!$row || (int) $row->nb !== 0) {
            throw new InvalidArgumentException('All imported accounts must be resolved before Apply');
        }
    }

    private function lockBookkeepingSequence($entity)
    {
        $sql = 'SELECT rowid,piece_num FROM '.$this->db->prefix().'accounting_bookkeeping';
        $sql .= ' WHERE entity = '.((int) $entity);
        $sql .= ' ORDER BY piece_num DESC,rowid DESC LIMIT 1 FOR UPDATE';

        if (!$this->db->query($sql)) {
            throw new RuntimeException('Unable to lock bookkeeping sequence: '.$this->db->lasterror());
        }
    }

    private function loadTransactions($importId)
    {
        $sql = 'SELECT rowid,external_transaction_id,journal_id,journal_description,transaction_date,registration_datetime,actor,description';
        $sql .= ' FROM '.$this->db->prefix().'dk_saft_import_transaction';
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

    private function loadTransactionLines($transactionId)
    {
        $sql = 'SELECT rowid,external_record_id,source_account_id,debit,credit,description';
        $sql .= ' FROM '.$this->db->prefix().'dk_saft_import_line';
        $sql .= ' WHERE fk_import_transaction = '.((int) $transactionId);
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

    private function loadResolvedAccount($importId, $sourceAccount)
    {
        $sql = 'SELECT description,resolved_local_account,mapping_status';
        $sql .= ' FROM '.$this->db->prefix().'dk_saft_import_account';
        $sql .= ' WHERE fk_import = '.((int) $importId);
        $sql .= " AND source_account_id = '".$this->db->escape($sourceAccount)."' LIMIT 1";

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RuntimeException($this->db->lasterror());
        }

        $row = $this->db->fetch_object($resql);
        if (!$row || empty($row->resolved_local_account)) {
            throw new RuntimeException('No resolved local account for imported account '.$sourceAccount);
        }

        return $row;
    }

    private function localAccountLabel($entity, $accountNumber, $fallback)
    {
        $sql = 'SELECT label FROM '.$this->db->prefix().'accounting_account';
        $sql .= ' WHERE entity = '.((int) $entity);
        $sql .= " AND account_number = '".$this->db->escape($accountNumber)."'";
        $sql .= ' AND active = 1 ORDER BY rowid ASC LIMIT 1';

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RuntimeException($this->db->lasterror());
        }

        $row = $this->db->fetch_object($resql);
        return $row && trim((string) $row->label) !== '' ? (string) $row->label : $fallback;
    }

    private function markLineApplied($stagedLineId, $bookkeepingRowId)
    {
        $sql = 'UPDATE '.$this->db->prefix().'dk_saft_import_line';
        $sql .= ' SET bookkeeping_rowid = '.((int) $bookkeepingRowId);
        $sql .= ' WHERE rowid = '.((int) $stagedLineId).' AND bookkeeping_rowid IS NULL';

        if (!$this->db->query($sql)) {
            throw new RuntimeException($this->db->lasterror());
        }
    }

    private function markTransactionApplied($transactionRowId, $pieceNum, $localRef)
    {
        $sql = 'UPDATE '.$this->db->prefix().'dk_saft_import_transaction';
        $sql .= ' SET local_piece_num = '.((int) $pieceNum);
        $sql .= ", local_ref = '".$this->db->escape($localRef)."'";
        $sql .= ' WHERE rowid = '.((int) $transactionRowId);

        if (!$this->db->query($sql)) {
            throw new RuntimeException($this->db->lasterror());
        }
    }

    private function buildLocalRef($importId, $sequence)
    {
        return substr('DKSAFT-'.$importId.'-'.$sequence, 0, 30);
    }

    private function buildImportKey($importId)
    {
        return substr('DKS'.((int) $importId), 0, 14);
    }

    private function journalCode($sourceJournal)
    {
        $sourceJournal = trim($sourceJournal);
        if ($sourceJournal === '') {
            return 'SAFT';
        }
        if (strlen($sourceJournal) > 32) {
            throw new InvalidArgumentException('Imported JournalID exceeds Dolibarr code_journal length');
        }
        return $sourceJournal;
    }
}
