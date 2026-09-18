<?php

dol_include_once('/dkmodul/class/Audit/AuditLedger.php');

/**
 * Records the traceability relation for accounting corrections.
 *
 * Creation of the actual reversal/replacement bookkeeping movements stays in
 * the accounting adapter. This service owns the compliance relation/evidence.
 */
class DkCorrectionService
{
    private $db;
    private $audit;

    public function __construct($db)
    {
        $this->db = $db;
        $this->audit = new DkAuditLedger($db);
    }

    public function record($entity, $originalPieceNum, $reversalPieceNum, $replacementPieceNum, $reason, $userId)
    {
        if ((int) $originalPieceNum <= 0 || (int) $reversalPieceNum <= 0) {
            throw new InvalidArgumentException('Original and reversal piece numbers are required');
        }
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Correction reason is required');
        }

        $sql = 'INSERT INTO '.$this->db->prefix().'dk_correction_link';
        $sql .= ' (entity,original_piece_num,reversal_piece_num,replacement_piece_num,reason,fk_user_author,date_creation)';
        $sql .= ' VALUES ('.((int) $entity);
        $sql .= ','.((int) $originalPieceNum);
        $sql .= ','.((int) $reversalPieceNum);
        $sql .= ','.($replacementPieceNum ? (int) $replacementPieceNum : 'NULL');
        $sql .= ",'".$this->db->escape(trim($reason))."'";
        $sql .= ','.((int) $userId);
        $sql .= ",'".$this->db->idate(dol_now())."')";

        if (!$this->db->query($sql)) {
            throw new RuntimeException('Unable to record correction relation: '.$this->db->lasterror());
        }

        $this->audit->append(
            (int) $entity,
            'bookkeeping.correction.recorded',
            'accounting_piece',
            (int) $originalPieceNum,
            (int) $userId,
            array(
                'original_piece_num' => (int) $originalPieceNum,
                'reversal_piece_num' => (int) $reversalPieceNum,
                'replacement_piece_num' => $replacementPieceNum ? (int) $replacementPieceNum : null,
                'reason' => trim($reason),
            )
        );

        return true;
    }
}
