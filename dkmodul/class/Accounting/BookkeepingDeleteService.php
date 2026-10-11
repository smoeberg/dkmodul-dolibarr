<?php

require_once __DIR__.'/../Audit/AuditLedger.php';

/**
 * The only supported application path for deleting bookkeeping rows.
 *
 * The delete connection is deliberately separate from Dolibarr's normal
 * database connection. The database guard checks CURRENT_USER(), so a normal
 * SQL session cannot self-authorize an unvalidated DELETE.
 */
class DkBookkeepingDeleteService
{
    private $db;
    private $audit;

    public function __construct($db, $audit = null, $deleteDb = null)
    {
        $this->db = $db;
        $this->deleteDb = $deleteDb !== null ? $deleteDb : $this->createControlledDeleteDb();
        $this->audit = $audit !== null ? $audit : new DkAuditLedger($this->deleteDb);
    }

    public function deleteByImportkey($entity, $importKey, $user)
    {
        return $this->deleteMatching(
            (int) $entity,
            'import_key = \''
                .$this->deleteDb->escape((string) $importKey)
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
                ." AND code_journal = '".$this->deleteDb->escape($journal)."'",
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

        $this->deleteDb->begin('DK controlled bookkeeping delete');

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

            $ids = implode(',', array_map('intval', array_column($rows, 'rowid')));
            $sql = 'DELETE FROM '.$this->deleteDb->prefix().'accounting_bookkeeping';
            $sql .= ' WHERE entity = '.$entity.' AND rowid IN ('.$ids.')';
            if (!$this->deleteDb->query($sql)) {
                throw new RuntimeException(
                    'Controlled bookkeeping delete failed: '.$this->deleteDb->lasterror()
                );
            }

            $this->deleteDb->commit('DK controlled bookkeeping delete');
            return count($rows);
        } catch (Throwable $e) {
            $this->deleteDb->rollback('DK controlled bookkeeping delete failed');
            throw new RuntimeException('DK controlled delete failed: '.$e->getMessage(), 0, $e);
        }
    }

    private function loadRowsForUpdate($entity, $where)
    {
        $sql = 'SELECT rowid,entity,ref,piece_num,doc_date,doc_type,doc_ref,fk_doc,fk_docdet,';
        $sql .= 'numero_compte,debit,credit,fk_user_author,date_creation,date_validated';
        $sql .= ' FROM '.$this->deleteDb->prefix().'accounting_bookkeeping';
        $sql .= ' WHERE entity = '.$entity.' AND '.$where;
        $sql .= ' ORDER BY rowid ASC FOR UPDATE';

        $resql = $this->deleteDb->query($sql);
        if (!$resql) {
            throw new RuntimeException('Unable to load bookkeeping rows: '.$this->deleteDb->lasterror());
        }

        $rows = array();
        while ($row = $this->deleteDb->fetch_object($resql)) {
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

    private function createControlledDeleteDb()
    {
        global $conf;

        $user = getenv('DOLI_DKMODUL_DELETE_DB_USER');
        $password = getenv('DOLI_DKMODUL_DELETE_DB_PASSWORD');

        if ($user === false || $password === false || $user === '' || $password === '') {
            throw new RuntimeException(
                'DK controlled bookkeeping delete requires dedicated database credentials'
            );
        }

        $type = isset($conf->db->type) ? $conf->db->type : '';
        if (!in_array(strtolower($type), array('mysqli', 'mysql'), true)) {
            throw new RuntimeException('DK controlled bookkeeping delete requires MariaDB/MySQL');
        }

        return getDoliDBInstance(
            $type,
            $conf->db->host,
            $user,
            $password,
            $conf->db->name,
            (int) $conf->db->port
        );
    }
}
