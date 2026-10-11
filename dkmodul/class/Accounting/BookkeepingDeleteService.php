<?php

require_once __DIR__.'/../Audit/AuditLedger.php';

/**
 * The only supported application path for deleting bookkeeping rows.
 *
 * Validated rows are always rejected. Unvalidated rows require a transaction-
 * scoped database context set immediately before the DELETE. The DB trigger
 * remains the final enforcement point for callers that bypass this service.
 */
class DkBookkeepingDeleteService
{
    private $db;
    private $audit;

    public function __construct($db, $audit = null)
    {
        $this->db = $db;
        $this->audit = $audit !== null ? $audit : new DkAuditLedger($db);
    }

    public function deleteByImportkey($entity, $importKey, $user)
    {
        return $this->deleteMatching(
            (int) $entity,
            'import_key = \''
                .$this->db->escape((string) $importKey)
                .'\'',
            $user,
            'import_key',
            (string) $importKey
        );
    }

    public function deleteByYearAndJournal($entity, $year, $journal, $user)
    {
        $year = (int) $year;
        $journal = (string) $journal;
        if ($year < 1 || $journal === '') {
            throw new InvalidArgumentException('Year and journal are required');
        }

        return $this->deleteMatching(
            (int) $entity,
            'YEAR(doc_date) = '.$year
                ." AND code_journal = '".$this->db->escape($journal)."'",
            $user,
            'year_and_journal',
            $year.'|'.$journal
        );
    }

    public function deleteMvtNum($entity, $pieceNum, $user)
    {
        return $this->deleteMatching(
            (int) $entity,
            'piece_num = '.((int) $pieceNum),
            $user,
            'piece_num',
            (string) ((int) $pieceNum)
        );
    }

    private function deleteMatching($entity, $where, $user, $selectorType, $selectorValue)
    {
        $entity = (int) $entity;
        if ($entity <= 0 || !is_object($user) || empty($user->id)) {
            throw new InvalidArgumentException('Entity and authenticated user are required');
        }

        $this->db->begin('DK controlled bookkeeping delete');

        try {
            $rows = $this->loadRowsForUpdate($entity, $where);
            if (!$rows) {
                throw new RuntimeException('No bookkeeping rows matched the controlled delete');
            }

            foreach ($rows as $row) {
                if (!empty($row->date_validated)) {
                    throw new RuntimeException(
                        'DK compliance: validated bookkeeping entries cannot be deleted'
                    );
                }
            }

            foreach ($rows as $row) {
                $this->audit->append(
                    $entity,
                    'bookkeeping.deleted',
                    'accounting_bookkeeping',
                    (int) $row->rowid,
                    (int) $user->id,
                    $this->beforeState($row),
                    array(
                        'selector_type' => $selectorType,
                        'selector' => $selectorValue,
                    )
                );
            }

            $nonce = bin2hex(random_bytes(32));
            $this->setDeleteContext($entity, (int) $user->id, $nonce);

            $ids = implode(',', array_map('intval', array_column($rows, 'rowid')));
            $sql = 'DELETE FROM '.$this->db->prefix().'accounting_bookkeeping';
            $sql .= ' WHERE entity = '.$entity.' AND rowid IN ('.$ids.')';
            if (!$this->db->query($sql)) {
                throw new RuntimeException(
                    'Controlled bookkeeping delete failed: '.$this->db->lasterror()
                );
            }

            $this->clearDeleteContext();
            $this->db->commit('DK controlled bookkeeping delete');

            return count($rows);
        } catch (Throwable $e) {
            $this->clearDeleteContext();
            $this->db->rollback('DK controlled bookkeeping delete failed');
            throw new RuntimeException('DK controlled delete failed: '.$e->getMessage(), 0, $e);
        }
    }

    private function loadRowsForUpdate($entity, $where)
    {
        $sql = 'SELECT rowid,entity,ref,piece_num,doc_date,doc_type,doc_ref,fk_doc,fk_docdet,';
        $sql .= 'numero_compte,debit,credit,fk_user_author,date_creation,date_validated';
        $sql .= ' FROM '.$this->db->prefix().'accounting_bookkeeping';
        $sql .= ' WHERE entity = '.$entity.' AND '.$where;
        $sql .= ' ORDER BY rowid ASC FOR UPDATE';

        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RuntimeException('Unable to load bookkeeping rows: '.$this->db->lasterror());
        }

        $rows = array();
        while ($row = $this->db->fetch_object($resql)) {
            $rows[] = $row;
        }

        return $rows;
    }

    private function beforeState($row)
    {
        return array(
            'rowid' => (int) $row->rowid,
            'entity' => (int) $row->entity,
            'ref' => (string) $row->ref,
            'piece_num' => (int) $row->piece_num,
            'doc_date' => (string) $row->doc_date,
            'doc_type' => (string) $row->doc_type,
            'doc_ref' => (string) $row->doc_ref,
            'fk_doc' => (int) $row->fk_doc,
            'fk_docdet' => (int) $row->fk_docdet,
            'account' => (string) $row->numero_compte,
            'debit' => $row->debit,
            'credit' => $row->credit,
            'fk_user_author' => (int) $row->fk_user_author,
            'date_creation' => (string) $row->date_creation,
            'date_validated' => $row->date_validated !== null ? (string) $row->date_validated : null,
        );
    }

    private function setDeleteContext($entity, $actorId, $nonce)
    {
        $sql = 'SET @dkmodul_controlled_delete_entity = '.((int) $entity);
        if (!$this->db->query($sql)) {
            throw new RuntimeException('Unable to establish controlled-delete entity context');
        }

        $sql = 'SET @dkmodul_controlled_delete_actor = '.((int) $actorId);
        if (!$this->db->query($sql)) {
            throw new RuntimeException('Unable to establish controlled-delete actor context');
        }

        $sql = "SET @dkmodul_controlled_delete_nonce = '".$this->db->escape($nonce)."'";
        if (!$this->db->query($sql)) {
            throw new RuntimeException('Unable to establish controlled-delete nonce');
        }
    }

    private function clearDeleteContext()
    {
        $this->db->query('SET @dkmodul_controlled_delete_entity = NULL');
        $this->db->query('SET @dkmodul_controlled_delete_actor = NULL');
        $this->db->query('SET @dkmodul_controlled_delete_nonce = NULL');
    }
}
