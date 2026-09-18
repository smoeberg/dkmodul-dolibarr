<?php

/**
 * Records explicit relationships between immutable original transactions and
 * new correction postings.
 */
class DkCorrectionService
{
    private $db;
    private $allowedTypes = array('reversal', 'adjustment', 'replacement');

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function record($entity, $originalPieceNum, $correctionPieceNum, $relationType, $reason, $userId)
    {
        $entity = (int) $entity;
        $originalPieceNum = (int) $originalPieceNum;
        $correctionPieceNum = (int) $correctionPieceNum;
        $userId = (int) $userId;
        $relationType = trim((string) $relationType);
        $reason = trim((string) $reason);

        if (!in_array($relationType, $this->allowedTypes, true)) {
            throw new InvalidArgumentException('Unsupported DK correction relation type');
        }
        if ($reason === '') {
            throw new InvalidArgumentException('A correction reason is required');
        }
        if ($originalPieceNum <= 0 || $correctionPieceNum <= 0 || $originalPieceNum === $correctionPieceNum) {
            throw new InvalidArgumentException('Original and correction transaction ids must be distinct positive values');
        }

        $this->assertOriginalIsValidated($entity, $originalPieceNum);
        $this->assertPieceExists($entity, $correctionPieceNum);

        $sql = 'INSERT INTO '.$this->db->prefix().'dk_correction';
        $sql .= ' (entity,original_piece_num,correction_piece_num,relation_type,reason,fk_user_author,date_creation)';
        $sql .= ' VALUES ('.$entity;
        $sql .= ','.$originalPieceNum;
        $sql .= ','.$correctionPieceNum;
        $sql .= ",'".$this->db->escape($relationType)."'";
        $sql .= ",'".$this->db->escape($reason)."'";
        $sql .= ','.$userId;
        $sql .= ",'".$this->db->idate(time())."')";

        if (!$this->db->query($sql)) {
            throw new RuntimeException('Unable to record DK correction relation: '.$this->db->lasterror());
        }

        return 1;
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

    private function assertPieceExists($entity, $pieceNum)
    {
        $sql = 'SELECT COUNT(*) AS total';
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
    }
}
