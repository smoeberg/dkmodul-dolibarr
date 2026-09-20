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
            $this->auditUpdateGuardSql(),
            $this->auditDeleteGuardSql(),
            $this->documentUpdateGuardSql(),
            $this->documentDeleteGuardSql(),
            $this->deliveryUpdateGuardSql(),
            $this->deliveryDeleteGuardSql(),
            $this->transportEventUpdateGuardSql(),
            $this->transportEventDeleteGuardSql(),
            $this->applicationResponseUpdateGuardSql(),
            $this->applicationResponseDeleteGuardSql(),
            $this->inboundUpdateGuardSql(),
            $this->inboundDeleteGuardSql(),
            $this->inboundValidationUpdateGuardSql(),
            $this->inboundValidationDeleteGuardSql(),
            $this->inboundDraftUpdateGuardSql(),
            $this->inboundDraftDeleteGuardSql(),
            $this->inboundSupplierValidationUpdateGuardSql(),
            $this->inboundSupplierValidationDeleteGuardSql(),
            $this->inboundPostingUpdateGuardSql(),
            $this->inboundPostingDeleteGuardSql(),
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

    public function auditUpdateGuardSql()
    {
        $audit = $this->db->prefix().'dk_audit_event';

        return 'CREATE TRIGGER '.$this->auditUpdateGuardName()
            .' BEFORE UPDATE ON '.$audit
            .' FOR EACH ROW BEGIN '
            ."SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DK compliance: audit events are append-only'; "
            .'END';
    }

    public function auditDeleteGuardSql()
    {
        $audit = $this->db->prefix().'dk_audit_event';

        return 'CREATE TRIGGER '.$this->auditDeleteGuardName()
            .' BEFORE DELETE ON '.$audit
            .' FOR EACH ROW BEGIN '
            ."SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DK compliance: audit events cannot be deleted'; "
            .'END';
    }

    public function documentUpdateGuardSql()
    {
        $documents = $this->db->prefix().'dk_document_archive';

        return 'CREATE TRIGGER '.$this->documentUpdateGuardName()
            .' BEFORE UPDATE ON '.$documents
            .' FOR EACH ROW BEGIN '
            ."SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DK compliance: archived document metadata is immutable'; "
            .'END';
    }

    public function documentDeleteGuardSql()
    {
        $documents = $this->db->prefix().'dk_document_archive';

        return 'CREATE TRIGGER '.$this->documentDeleteGuardName()
            .' BEFORE DELETE ON '.$documents
            .' FOR EACH ROW BEGIN '
            ."SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DK compliance: archived documents cannot be deleted'; "
            .'END';
    }

    public function deliveryUpdateGuardSql()
    {
        return $this->appendOnlyGuardSql($this->deliveryUpdateGuardName(), $this->db->prefix().'dk_einvoice_delivery', 'UPDATE', 'e-invoice deliveries are immutable');
    }

    public function deliveryDeleteGuardSql()
    {
        return $this->appendOnlyGuardSql($this->deliveryDeleteGuardName(), $this->db->prefix().'dk_einvoice_delivery', 'DELETE', 'e-invoice deliveries cannot be deleted');
    }

    public function transportEventUpdateGuardSql()
    {
        return $this->appendOnlyGuardSql($this->transportEventUpdateGuardName(), $this->db->prefix().'dk_einvoice_transport_event', 'UPDATE', 'e-invoice transport events are append-only');
    }

    public function transportEventDeleteGuardSql()
    {
        return $this->appendOnlyGuardSql($this->transportEventDeleteGuardName(), $this->db->prefix().'dk_einvoice_transport_event', 'DELETE', 'e-invoice transport events cannot be deleted');
    }

    public function applicationResponseUpdateGuardSql()
    {
        return $this->appendOnlyGuardSql($this->applicationResponseUpdateGuardName(), $this->db->prefix().'dk_einvoice_application_response', 'UPDATE', 'OIOUBL application responses are append-only');
    }

    public function applicationResponseDeleteGuardSql()
    {
        return $this->appendOnlyGuardSql($this->applicationResponseDeleteGuardName(), $this->db->prefix().'dk_einvoice_application_response', 'DELETE', 'OIOUBL application responses cannot be deleted');
    }

    public function inboundUpdateGuardSql()
    {
        return $this->appendOnlyGuardSql($this->inboundUpdateGuardName(), $this->db->prefix().'dk_einvoice_inbound', 'UPDATE', 'inbound e-invoices are immutable');
    }

    public function inboundDeleteGuardSql()
    {
        return $this->appendOnlyGuardSql($this->inboundDeleteGuardName(), $this->db->prefix().'dk_einvoice_inbound', 'DELETE', 'inbound e-invoices cannot be deleted');
    }

    public function inboundValidationUpdateGuardSql()
    {
        return $this->appendOnlyGuardSql($this->inboundValidationUpdateGuardName(), $this->db->prefix().'dk_einvoice_inbound_validation', 'UPDATE', 'inbound validation evidence is immutable');
    }

    public function inboundValidationDeleteGuardSql()
    {
        return $this->appendOnlyGuardSql($this->inboundValidationDeleteGuardName(), $this->db->prefix().'dk_einvoice_inbound_validation', 'DELETE', 'inbound validation evidence cannot be deleted');
    }

    public function inboundDraftUpdateGuardSql()
    {
        return $this->appendOnlyGuardSql($this->inboundDraftUpdateGuardName(), $this->db->prefix().'dk_einvoice_inbound_draft', 'UPDATE', 'inbound supplier draft provenance is immutable');
    }

    public function inboundDraftDeleteGuardSql()
    {
        return $this->appendOnlyGuardSql($this->inboundDraftDeleteGuardName(), $this->db->prefix().'dk_einvoice_inbound_draft', 'DELETE', 'inbound supplier draft provenance cannot be deleted');
    }

    public function inboundSupplierValidationUpdateGuardSql()
    {
        return $this->appendOnlyGuardSql($this->inboundSupplierValidationUpdateGuardName(), $this->db->prefix().'dk_einvoice_inbound_supplier_validation', 'UPDATE', 'supplier invoice validation evidence is immutable');
    }

    public function inboundSupplierValidationDeleteGuardSql()
    {
        return $this->appendOnlyGuardSql($this->inboundSupplierValidationDeleteGuardName(), $this->db->prefix().'dk_einvoice_inbound_supplier_validation', 'DELETE', 'supplier invoice validation evidence cannot be deleted');
    }

    public function inboundPostingUpdateGuardSql()
    {
        return $this->appendOnlyGuardSql($this->inboundPostingUpdateGuardName(), $this->db->prefix().'dk_einvoice_inbound_posting', 'UPDATE', 'inbound supplier posting evidence is immutable');
    }

    public function inboundPostingDeleteGuardSql()
    {
        return $this->appendOnlyGuardSql($this->inboundPostingDeleteGuardName(), $this->db->prefix().'dk_einvoice_inbound_posting', 'DELETE', 'inbound supplier posting evidence cannot be deleted');
    }

    private function appendOnlyGuardSql(string $name, string $table, string $operation, string $message): string
    {
        return 'CREATE TRIGGER '.$name.' BEFORE '.$operation.' ON '.$table
            .' FOR EACH ROW BEGIN '
            ."SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DK compliance: ".$message."'; "
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
            $this->auditUpdateGuardName(),
            $this->auditDeleteGuardName(),
            $this->documentUpdateGuardName(),
            $this->documentDeleteGuardName(),
            $this->deliveryUpdateGuardName(),
            $this->deliveryDeleteGuardName(),
            $this->transportEventUpdateGuardName(),
            $this->transportEventDeleteGuardName(),
            $this->applicationResponseUpdateGuardName(),
            $this->applicationResponseDeleteGuardName(),
            $this->inboundUpdateGuardName(),
            $this->inboundDeleteGuardName(),
            $this->inboundValidationUpdateGuardName(),
            $this->inboundValidationDeleteGuardName(),
            $this->inboundDraftUpdateGuardName(),
            $this->inboundDraftDeleteGuardName(),
            $this->inboundSupplierValidationUpdateGuardName(),
            $this->inboundSupplierValidationDeleteGuardName(),
            $this->inboundPostingUpdateGuardName(),
            $this->inboundPostingDeleteGuardName(),
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

    private function auditUpdateGuardName()
    {
        return $this->db->prefix().'dk_audit_event_bu';
    }

    private function auditDeleteGuardName()
    {
        return $this->db->prefix().'dk_audit_event_bd';
    }

    private function documentUpdateGuardName()
    {
        return $this->db->prefix().'dk_document_archive_bu';
    }

    private function documentDeleteGuardName()
    {
        return $this->db->prefix().'dk_document_archive_bd';
    }

    private function deliveryUpdateGuardName()
    {
        return $this->db->prefix().'dk_einvoice_delivery_bu';
    }

    private function deliveryDeleteGuardName()
    {
        return $this->db->prefix().'dk_einvoice_delivery_bd';
    }

    private function transportEventUpdateGuardName()
    {
        return $this->db->prefix().'dk_einvoice_transport_event_bu';
    }

    private function transportEventDeleteGuardName()
    {
        return $this->db->prefix().'dk_einvoice_transport_event_bd';
    }

    private function applicationResponseUpdateGuardName()
    {
        return $this->db->prefix().'dk_einvoice_application_response_bu';
    }

    private function applicationResponseDeleteGuardName()
    {
        return $this->db->prefix().'dk_einvoice_application_response_bd';
    }

    private function inboundUpdateGuardName()
    {
        return $this->db->prefix().'dk_einvoice_inbound_bu';
    }

    private function inboundDeleteGuardName()
    {
        return $this->db->prefix().'dk_einvoice_inbound_bd';
    }

    private function inboundValidationUpdateGuardName()
    {
        return $this->db->prefix().'dk_einvoice_inbound_validation_bu';
    }

    private function inboundValidationDeleteGuardName()
    {
        return $this->db->prefix().'dk_einvoice_inbound_validation_bd';
    }

    private function inboundDraftUpdateGuardName()
    {
        return $this->db->prefix().'dk_einvoice_inbound_draft_bu';
    }

    private function inboundDraftDeleteGuardName()
    {
        return $this->db->prefix().'dk_einvoice_inbound_draft_bd';
    }

    private function inboundSupplierValidationUpdateGuardName()
    {
        return $this->db->prefix().'dk_einvoice_inbound_supplier_validation_bu';
    }

    private function inboundSupplierValidationDeleteGuardName()
    {
        return $this->db->prefix().'dk_einvoice_inbound_supplier_validation_bd';
    }

    private function inboundPostingUpdateGuardName()
    {
        return $this->db->prefix().'dk_einvoice_inbound_posting_bu';
    }

    private function inboundPostingDeleteGuardName()
    {
        return $this->db->prefix().'dk_einvoice_inbound_posting_bd';
    }

    private function assertSupportedDatabase()
    {
        $type = isset($this->db->type) ? strtolower((string) $this->db->type) : '';

        if (!in_array($type, array('mysqli', 'mysql'), true)) {
            throw new RuntimeException('Dolibarr DK initial certified profile requires MariaDB/MySQL');
        }
    }
}
