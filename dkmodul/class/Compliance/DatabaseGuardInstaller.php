<?php

/**
 * Installs MariaDB/MySQL database triggers that make validated bookkeeping rows immutable.
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

        foreach (array($this->updateTriggerSql(), $this->deleteTriggerSql()) as $sql) {
            if (!$this->db->query($sql)) {
                throw new RuntimeException('Unable to install DK bookkeeping database guard: '.$this->db->lasterror());
            }
        }

        return 1;
    }

    public function drop()
    {
        $this->assertSupportedDatabase();

        foreach (array($this->updateTriggerName(), $this->deleteTriggerName()) as $name) {
            if (!$this->db->query('DROP TRIGGER IF EXISTS '.$name)) {
                throw new RuntimeException('Unable to remove DK bookkeeping database guard: '.$this->db->lasterror());
            }
        }

        return 1;
    }

    public function updateTriggerSql()
    {
        $table = $this->db->prefix().'accounting_bookkeeping';

        $columns = array(
            'entity', 'ref', 'piece_num', 'doc_date', 'doc_type', 'doc_ref', 'fk_doc', 'fk_docdet',
            'thirdparty_code', 'subledger_account', 'subledger_label', 'numero_compte', 'label_compte',
            'label_operation', 'debit', 'credit', 'montant', 'sens', 'multicurrency_amount',
            'multicurrency_code', 'matching_general', 'lettering_code', 'date_lettering',
            'date_lim_reglement', 'fk_user_author', 'fk_user_modif', 'date_creation', 'fk_user',
            'code_journal', 'journal_label', 'date_validated', 'import_key', 'extraparams',
        );

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

    private function updateTriggerName()
    {
        return $this->db->prefix().'dk_bookkeeping_lock_bu';
    }

    private function deleteTriggerName()
    {
        return $this->db->prefix().'dk_bookkeeping_lock_bd';
    }

    private function assertSupportedDatabase()
    {
        $type = isset($this->db->type) ? strtolower((string) $this->db->type) : '';

        if (!in_array($type, array('mysqli', 'mysql'), true)) {
            throw new RuntimeException('Dolibarr DK initial certified profile requires MariaDB/MySQL');
        }
    }
}
