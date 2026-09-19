<?php

require_once __DIR__.'/../Audit/AuditLedger.php';

/**
 * Creates traceable corrections without mutating validated bookkeeping rows.
 */
class DkCorrectionService
{
    private $db;
    private $audit;
    private $allowedTypes = array('reversal', 'adjustment', 'replacement');

    public function __construct($db)
    {
        $this->db = $db;
        $this->audit = new DkAuditLedger($db);
    }

    /**
     * Create and validate a balanced reversal of an existing validated piece.
     *
     * @return array{originalPieceNum:int,reversalPieceNum:int,lineCount:int}
     */
    public function reverse($entity, $originalPieceNum, $reason, $user)
    {
        $entity = (int) $entity;
        $originalPieceNum = (int) $originalPieceNum;
        $reason = trim((string) $reason);

        if ($entity <= 0 || $originalPieceNum <= 0 || !is_object($user) || empty($user->id)) {
            throw new InvalidArgumentException('Entity, original piece and correcting user are required');
        }
        if ($reason === '') {
            throw new InvalidArgumentException('A correction reason is required');
        }

        require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';
        require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

        $this->db->begin('DK correction reversal');

        try {
            $rows = $this->loadValidatedPieceForUpdate($entity, $originalPieceNum);
            $this->assertPieceBalanced($rows, $originalPieceNum);
            $this->assertNoExistingReversal($entity, $originalPieceNum);
            $this->lockBookkeepingSequence($entity);

            $allocator = new BookKeeping($this->db);
            $reversalPieceNum = (int) $allocator->getNextNumMvt();
            if ($reversalPieceNum <= 0 || $reversalPieceNum === $originalPieceNum) {
                throw new RuntimeException('Unable to allocate correction piece number');
            }

            $createdRows = array();
            foreach ($rows as $row) {
                $bookkeeping = new BookKeeping($this->db);
                $bookkeeping->doc_date = $this->db->jdate($row->doc_date);
                $bookkeeping->doc_type = 'dk_correction';
                $bookkeeping->doc_ref = 'REV-'.$originalPieceNum;
                $bookkeeping->fk_doc = $originalPieceNum;
                $bookkeeping->fk_docdet = (int) $row->rowid;
                $bookkeeping->thirdparty_code = (string) $row->thirdparty_code;
                $bookkeeping->subledger_account = (string) $row->subledger_account;
                $bookkeeping->subledger_label = (string) $row->subledger_label;
                $bookkeeping->numero_compte = (string) $row->numero_compte;
                $bookkeeping->label_compte = (string) $row->label_compte;
                $bookkeeping->label_operation = 'Reversal '.$originalPieceNum.': '.(string) $row->label_operation;
                $bookkeeping->debit = (float) $row->credit;
                $bookkeeping->credit = (float) $row->debit;
                $bookkeeping->montant = $bookkeeping->debit > 0
                    ? $bookkeeping->debit
                    : -1 * $bookkeeping->credit;
                $bookkeeping->sens = $bookkeeping->debit > 0 ? 'D' : 'C';
                $bookkeeping->import_key = '';
                $bookkeeping->code_journal = (string) $row->code_journal;
                $bookkeeping->journal_label = (string) $row->journal_label;
                $bookkeeping->piece_num = $reversalPieceNum;
                $bookkeeping->ref = 'DK-REV-'.$reversalPieceNum;
                $bookkeeping->entity = $entity;

                $rowId = $bookkeeping->createStd($user, 0, '');
                if ($rowId <= 0) {
                    $errors = !empty($bookkeeping->errors)
                        ? implode('; ', $bookkeeping->errors)
                        : (string) $bookkeeping->error;
                    throw new RuntimeException(
                        'Dolibarr rejected correction line'.($errors !== '' ? ': '.$errors : '')
                    );
                }
                $createdRows[] = (int) $rowId;
            }

            $this->validateCreatedRows($entity, $reversalPieceNum, $createdRows);
            $this->insertRelation(
                $entity,
                $originalPieceNum,
                $reversalPieceNum,
                'reversal',
                $reason,
                (int) $user->id
            );
            $this->appendAudit(
                $entity,
                $originalPieceNum,
                $reversalPieceNum,
                'reversal',
                $reason,
                (int) $user->id,
                count($createdRows)
            );

            $this->db->commit('DK correction reversal');

            return array(
                'originalPieceNum' => $originalPieceNum,
                'reversalPieceNum' => $reversalPieceNum,
                'lineCount' => count($createdRows),
            );
        } catch (Throwable $e) {
            $this->db->rollback('DK correction reversal failed');
            throw new RuntimeException('DK correction failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Record a relation to an already-created correction piece.
     */
    public function record($entity, $originalPieceNum, $correctionPieceNum, $relationType, $reason, $userId)
    {
        $entity = (int) $entity;
        $originalPieceNum = (int) $originalPieceNum;
        $correctionPieceNum = (int) $correctionPieceNum;
        $userId = (int) $userId;
        $relationType = trim((string) $relationType);
        $reason = trim((string) $reason);

        $this->assertValidRelationInput(
            $entity,
            $originalPieceNum,
            $correctionPieceNum,
            $relationType,
            $reason,
            $userId
        );
        $this->db->begin('DK correction relation');
        try {
            $this->assertOriginalIsValidated($entity, $originalPieceNum);
            $this->assertPieceIsValidated($entity, $correctionPieceNum);
            if ($relationType === 'reversal') {
                $this->assertNoExistingReversal($entity, $originalPieceNum);
            }
            $this->insertRelation(
                $entity,
                $originalPieceNum,
                $correctionPieceNum,
                $relationType,
                $reason,
                $userId
            );
            $this->appendAudit(
                $entity,
                $originalPieceNum,
                $correctionPieceNum,
                $relationType,
                $reason,
                $userId,
                null
            );
            $this->db->commit('DK correction relation');
        } catch (Throwable $e) {
            $this->db->rollback('DK correction relation failed');
            throw $e;
        }

        return 1;
    }

    private function assertValidRelationInput($entity, $originalPieceNum, $correctionPieceNum, $relationType, $reason, $userId)
    {
        if (!in_array($relationType, $this->allowedTypes, true)) {
            throw new InvalidArgumentException('Unsupported DK correction relation type');
        }
        if ($reason === '') {
            throw new InvalidArgumentException('A correction reason is required');
        }
        if ($entity <= 0 || $userId <= 0) {
            throw new InvalidArgumentException('Entity and correcting user are required');
        }
        if ($originalPieceNum <= 0 || $correctionPieceNum <= 0 || $originalPieceNum === $correctionPieceNum) {
            throw new InvalidArgumentException('Original and correction transaction ids must be distinct positive values');
        }
    }

    private function loadValidatedPieceForUpdate($entity, $pieceNum)
    {
        $sql = 'SELECT rowid,doc_date,thirdparty_code,subledger_account,subledger_label,';
        $sql .= ' numero_compte,label_compte,label_operation,debit,credit,code_journal,journal_label,date_validated';
        $sql .= ' FROM '.$this->db->prefix().'accounting_bookkeeping';
        $sql .= ' WHERE entity = '.((int) $entity);
        $sql .= ' AND piece_num = '.((int) $pieceNum);
        $sql .= ' ORDER BY rowid ASC FOR UPDATE';

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RuntimeException('Unable to load original bookkeeping transaction: '.$this->db->lasterror());
        }

        $rows = array();
        while ($row = $this->db->fetch_object($resql)) {
            if (empty($row->date_validated)) {
                throw new RuntimeException('Original bookkeeping transaction must be fully validated before correction');
            }
            $rows[] = $row;
        }
        if (!$rows) {
            throw new RuntimeException('Original bookkeeping transaction does not exist');
        }

        return $rows;
    }

    private function assertPieceBalanced(array $rows, $pieceNum)
    {
        $debit = 0.0;
        $credit = 0.0;
        foreach ($rows as $row) {
            $debit += (float) $row->debit;
            $credit += (float) $row->credit;
        }
        if (abs($debit - $credit) > 0.00001) {
            throw new RuntimeException('Original bookkeeping transaction '.$pieceNum.' is not balanced');
        }
    }

    private function assertNoExistingReversal($entity, $pieceNum)
    {
        $sql = 'SELECT rowid FROM '.$this->db->prefix().'dk_correction';
        $sql .= ' WHERE entity = '.((int) $entity);
        $sql .= ' AND original_piece_num = '.((int) $pieceNum);
        $sql .= " AND relation_type = 'reversal' LIMIT 1 FOR UPDATE";

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RuntimeException('Unable to inspect correction history: '.$this->db->lasterror());
        }
        if ($this->db->fetch_object($resql)) {
            throw new RuntimeException('Original bookkeeping transaction already has a reversal');
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

    private function validateCreatedRows($entity, $pieceNum, array $rowIds)
    {
        if (!$rowIds) {
            throw new RuntimeException('Correction produced no bookkeeping rows');
        }

        $sql = 'UPDATE '.$this->db->prefix().'accounting_bookkeeping';
        $sql .= ' SET date_validated = NOW()';
        $sql .= ' WHERE entity = '.((int) $entity);
        $sql .= ' AND piece_num = '.((int) $pieceNum);
        $sql .= ' AND rowid IN ('.implode(',', array_map('intval', $rowIds)).')';
        $sql .= ' AND date_validated IS NULL';

        if (!$this->db->query($sql)) {
            throw new RuntimeException('Unable to validate correction rows: '.$this->db->lasterror());
        }
    }

    private function insertRelation($entity, $originalPieceNum, $correctionPieceNum, $relationType, $reason, $userId)
    {
        $sql = 'INSERT INTO '.$this->db->prefix().'dk_correction';
        $sql .= ' (entity,original_piece_num,correction_piece_num,relation_type,reason,fk_user_author,date_creation)';
        $sql .= ' VALUES ('.((int) $entity);
        $sql .= ','.((int) $originalPieceNum);
        $sql .= ','.((int) $correctionPieceNum);
        $sql .= ",'".$this->db->escape($relationType)."'";
        $sql .= ",'".$this->db->escape($reason)."'";
        $sql .= ','.((int) $userId);
        $sql .= ",'".$this->db->idate(time())."')";

        if (!$this->db->query($sql)) {
            throw new RuntimeException('Unable to record DK correction relation: '.$this->db->lasterror());
        }
    }

    private function appendAudit($entity, $originalPieceNum, $correctionPieceNum, $relationType, $reason, $userId, $lineCount)
    {
        $payload = array(
            'original_piece_num' => (int) $originalPieceNum,
            'correction_piece_num' => (int) $correctionPieceNum,
            'relation_type' => (string) $relationType,
            'reason' => (string) $reason,
        );
        if ($lineCount !== null) {
            $payload['line_count'] = (int) $lineCount;
        }

        $this->audit->append(
            (int) $entity,
            'bookkeeping.correction.recorded',
            'accounting_piece',
            (int) $originalPieceNum,
            (int) $userId,
            $payload
        );
    }

    private function assertOriginalIsValidated($entity, $pieceNum)
    {
        $sql = 'SELECT COUNT(*) AS total, SUM(CASE WHEN date_validated IS NOT NULL THEN 1 ELSE 0 END) AS validated';
        $sql .= ' FROM '.$this->db->prefix().'accounting_bookkeeping';
        $sql .= ' WHERE entity = '.((int) $entity);
        $sql .= ' AND piece_num = '.((int) $pieceNum);

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RuntimeException('Unable to inspect original bookkeeping transaction: '.$this->db->lasterror());
        }

        $row = $this->db->fetch_object($resql);
        if (!$row || (int) $row->total === 0) {
            throw new RuntimeException('Original bookkeeping transaction does not exist');
        }
        if ((int) $row->validated !== (int) $row->total) {
            throw new RuntimeException('Original bookkeeping transaction must be fully validated before correction');
        }
    }

    private function assertPieceIsValidated($entity, $pieceNum)
    {
        $sql = 'SELECT COUNT(*) AS total, SUM(CASE WHEN date_validated IS NOT NULL THEN 1 ELSE 0 END) AS validated';
        $sql .= ' FROM '.$this->db->prefix().'accounting_bookkeeping';
        $sql .= ' WHERE entity = '.((int) $entity);
        $sql .= ' AND piece_num = '.((int) $pieceNum);

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RuntimeException('Unable to inspect correcting bookkeeping transaction: '.$this->db->lasterror());
        }

        $row = $this->db->fetch_object($resql);
        if (!$row || (int) $row->total === 0) {
            throw new RuntimeException('Correcting bookkeeping transaction does not exist');
        }
        if ((int) $row->validated !== (int) $row->total) {
            throw new RuntimeException('Correcting bookkeeping transaction must be fully validated');
        }
    }
}
