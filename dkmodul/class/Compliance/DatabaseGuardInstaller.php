<?php

/**
 * Installs MariaDB/MySQL controls for immutable bookkeeping and provenance.
 */
class DkDatabaseGuardInstaller
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function install()
    {
        $this->assertSupportedDatabase();
        $this->drop();

        $sqlStatements = array(
            $this->updateTriggerSql(),
            $this->deleteTriggerSql(),
            $this->insertProvenanceTriggerSql(),
            $this->provenanceUpdateGuardSql(),
            $this->provenanceDeleteGuardSql(),
        );

        foreach ($sqlStatements as $sql) {
            if (!$this->db->query($sql)) {
                throw new RuntimeException('Unable to install DK bookkeeping database control: '.$this->db->lasterror());
            }
        }

        $this->backfillProvenance();

        return 1;
    }

    public function drop()
    {
        $this->assertSupportedDatabase();

        foreach ($this->triggerNames() as $name) {
            if (!$this->db->query('DROP TRIGGER IF EXISTS '.$name)) {
                throw new RuntimeException('Unable to remove DK bookkeeping database control: '.$this->db->lasterror());
            }
        }

        return 1;
    }

    public function updateTriggerSql()
    {
        $table = $this->db->prefix().'accounting_bookkeeping';

        $columns = $this->protectedBookkeepingColumns();
        $comparisons = array();
        foreach ($columns as $column) {
            $comparisons[] = 'NOT (NEW.'.$column.' <=> OLD.'.$column.')';
        }

        return 'CREATE TRIGGER '.$this->updateTriggerName()
            .' BEFORE UPDATE ON '.$table
            .' FOR EACH ROW BEGIN '
            .'IF OLD.date_validated IS NOT NULL AND ('.implode(' OR ', $comparisons).') THEN '
            ."SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DK compliance: validated bookkeeping entries are immutable'; "
            .'END IF; END';
    }

    public function deleteTriggerSql()
    {
        $table = $this->db->prefix().'accounting_bookkeeping';

        return 'CREATE TRIGGER '.$this->deleteTriggerName()
            .' BEFORE DELETE ON '.$table
            .' FOR EACH ROW BEGIN '
            .'IF OLD.date_validated IS NOT NULL THEN '
            ."SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DK compliance: validated bookkeeping entries cannot be deleted'; "
            .'END IF; END';
    }

    public function insertProvenanceTriggerSql()
    {
        $table = $this->db->prefix().'accounting_bookkeeping';
        $origin = $this->db->prefix().'dk_bookkeeping_origin';

        return 'CREATE TRIGGER '.$this->insertProvenanceTriggerName()
            .' AFTER INSERT ON '.$table
            .' FOR EACH ROW BEGIN '
            .'INSERT INTO '.$origin
            .' (entity,bookkeeping_rowid,piece_num,fk_user_author,database_user,origin_type,recorded_at,content_hash)'
            .' VALUES (NEW.entity,NEW.rowid,NEW.piece_num,NEW.fk_user_author,CURRENT_USER(),'
            ."'insert',NOW(),".$this->snapshotHashSql('NEW').'); '
            .'END';
    }

    public function provenanceUpdateGuardSql()
    {
        $origin = $this->db->prefix().'dk_bookkeeping_origin';

        return 'CREATE TRIGGER '.$this->provenanceUpdateGuardName()
            .' BEFORE UPDATE ON '.$origin
            .' FOR EACH ROW BEGIN '
            ."SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DK compliance: bookkeeping provenance is append-only'; "
            .'END';
    }

    public function provenanceDeleteGuardSql()
    {
        $origin = $this->db->prefix().'dk_bookkeeping_origin';

        return 'CREATE TRIGGER '.$this->provenanceDeleteGuardName()
            .' BEFORE DELETE ON '.$origin
            .' FOR EACH ROW BEGIN '
            ."SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DK compliance: bookkeeping provenance cannot be deleted'; "
            .'END';
    }

    public function backfillProvenance()
    {
        $bookkeeping = $this->db->prefix().'accounting_bookkeeping';
        $origin = $this->db->prefix().'dk_bookkeeping_origin';

        $sql = 'INSERT IGNORE INTO '.$origin;
        $sql .= ' (entity,bookkeeping_rowid,piece_num,fk_user_author,database_user,origin_type,recorded_at,content_hash)';
        $sql .= ' SELECT b.entity,b.rowid,b.piece_num,b.fk_user_author,CURRENT_USER(),';
        $sql .= " 'backfill',COALESCE(b.date_creation,NOW()),".$this->snapshotHashSql('b');
        $sql .= ' FROM '.$bookkeeping.' b';

        if (!$this->db->query($sql)) {
            throw new RuntimeException('Unable to backfill DK bookkeeping provenance: '.$this->db->lasterror());
        }

        return 1;
    }

    private function snapshotHashSql($alias)
    {
        $parts = array();
        foreach ($this->protectedBookkeepingColumns() as $column) {
            $parts[] = "COALESCE(CAST(".$alias.'.'.$column." AS CHAR),'∅')";
        }

        return "SHA2(CONCAT_WS('|',".implode(',', $parts).'),256)';
    }

    private function protectedBookkeepingColumns()
    {
        return array(
            'entity', 'ref', 'piece_num', 'doc_date', 'doc_type', 'doc_ref', 'fk_doc', 'fk_docdet',
            'thirdparty_code', 'subledger_account', 'subledger_label', 'numero_compte', 'label_compte',
            'label_operation', 'debit', 'credit', 'montant', 'sens', 'multicurrency_amount',
            'multicurrency_code', 'matching_general', 'lettering_code', 'date_lettering',
            'date_lim_reglement', 'fk_user_author', 'fk_user_modif', 'date_creation', 'fk_user',
            'code_journal', 'journal_label', 'date_validated', 'import_key', 'extraparams',
        );
    }

    private function triggerNames()
    {
        return array(
            $this->updateTriggerName(),
            $this->deleteTriggerName(),
            $this->insertProvenanceTriggerName(),
            $this->provenanceUpdateGuardName(),
            $this->provenanceDeleteGuardName(),
        );
    }

    private function updateTriggerName()
    {
        return $this->db->prefix().'dk_bookkeeping_lock_bu';
    }

    private function deleteTriggerName()
    {
        return $this->db->prefix().'dk_bookkeeping_lock_bd';
    }

    private function insertProvenanceTriggerName()
    {
        return $this->db->prefix().'dk_bookkeeping_origin_ai';
    }

    private function provenanceUpdateGuardName()
    {
        return $this->db->prefix().'dk_bookkeeping_origin_bu';
    }

    private function provenanceDeleteGuardName()
    {
        return $this->db->prefix().'dk_bookkeeping_origin_bd';
    }

    private function assertSupportedDatabase()
    {
        $type = isset($this->db->type) ? strtolower((string) $this->db->type) : '';

        if (!in_array($type, array('mysqli', 'mysql'), true)) {
            throw new RuntimeException('Dolibarr DK initial certified profile requires MariaDB/MySQL');
        }
    }
}
