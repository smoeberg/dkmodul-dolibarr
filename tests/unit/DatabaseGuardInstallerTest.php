<?php

require_once __DIR__.'/../../dkmodul/class/Compliance/DatabaseGuardInstaller.php';

class DkFakeDb
{
    public $type = 'mysqli';

    public function prefix()
    {
        return 'llx_';
    }
}

$installer = new DkDatabaseGuardInstaller(new DkFakeDb());
$updateSql = $installer->updateTriggerSql();
$deleteSql = $installer->deleteTriggerSql();
$originSql = $installer->insertProvenanceTriggerSql();
$originUpdateSql = $installer->provenanceUpdateGuardSql();
$originDeleteSql = $installer->provenanceDeleteGuardSql();
$auditUpdateSql = $installer->auditUpdateGuardSql();
$auditDeleteSql = $installer->auditDeleteGuardSql();
$documentUpdateSql = $installer->documentUpdateGuardSql();
$documentDeleteSql = $installer->documentDeleteGuardSql();
$deliveryUpdateSql = $installer->deliveryUpdateGuardSql();
$deliveryDeleteSql = $installer->deliveryDeleteGuardSql();
$transportEventUpdateSql = $installer->transportEventUpdateGuardSql();
$transportEventDeleteSql = $installer->transportEventDeleteGuardSql();
$inboundUpdateSql = $installer->inboundUpdateGuardSql();
$inboundDeleteSql = $installer->inboundDeleteGuardSql();
$inboundValidationUpdateSql = $installer->inboundValidationUpdateGuardSql();
$inboundValidationDeleteSql = $installer->inboundValidationDeleteGuardSql();
$inboundDraftUpdateSql = $installer->inboundDraftUpdateGuardSql();
$inboundDraftDeleteSql = $installer->inboundDraftDeleteGuardSql();

assert(strpos($updateSql, 'BEFORE UPDATE ON llx_accounting_bookkeeping') !== false);
assert(strpos($updateSql, 'OLD.date_validated IS NOT NULL') !== false);
assert(strpos($updateSql, 'NEW.debit') !== false);
assert(strpos($updateSql, 'NEW.date_validated') !== false);
assert(strpos($updateSql, 'SIGNAL SQLSTATE') !== false);
assert(strpos($updateSql, 'NEW.date_export') === false);
assert(strpos($updateSql, 'NEW.tms') === false);

assert(strpos($deleteSql, 'BEFORE DELETE ON llx_accounting_bookkeeping') !== false);
assert(strpos($deleteSql, 'OLD.date_validated IS NOT NULL') !== false);
assert(strpos($deleteSql, 'SIGNAL SQLSTATE') !== false);

assert(strpos($originSql, 'AFTER INSERT ON llx_accounting_bookkeeping') !== false);
assert(strpos($originSql, 'llx_dk_bookkeeping_origin') !== false);
assert(strpos($originSql, 'NEW.fk_user_author') !== false);
assert(strpos($originSql, 'CURRENT_USER()') !== false);
assert(strpos($originSql, 'SHA2(') !== false);

assert(strpos($originUpdateSql, 'BEFORE UPDATE ON llx_dk_bookkeeping_origin') !== false);
assert(strpos($originDeleteSql, 'BEFORE DELETE ON llx_dk_bookkeeping_origin') !== false);

assert(strpos($auditUpdateSql, 'BEFORE UPDATE ON llx_dk_audit_event') !== false);
assert(strpos($auditUpdateSql, 'append-only') !== false);
assert(strpos($auditDeleteSql, 'BEFORE DELETE ON llx_dk_audit_event') !== false);
assert(strpos($auditDeleteSql, 'cannot be deleted') !== false);

assert(strpos($documentUpdateSql, 'BEFORE UPDATE ON llx_dk_document_archive') !== false);
assert(strpos($documentUpdateSql, 'metadata is immutable') !== false);
assert(strpos($documentDeleteSql, 'BEFORE DELETE ON llx_dk_document_archive') !== false);
assert(strpos($documentDeleteSql, 'cannot be deleted') !== false);

assert(strpos($deliveryUpdateSql, 'BEFORE UPDATE ON llx_dk_einvoice_delivery') !== false);
assert(strpos($deliveryDeleteSql, 'BEFORE DELETE ON llx_dk_einvoice_delivery') !== false);
assert(strpos($transportEventUpdateSql, 'BEFORE UPDATE ON llx_dk_einvoice_transport_event') !== false);
assert(strpos($transportEventUpdateSql, 'append-only') !== false);
assert(strpos($transportEventDeleteSql, 'BEFORE DELETE ON llx_dk_einvoice_transport_event') !== false);

assert(strpos($inboundUpdateSql, 'BEFORE UPDATE ON llx_dk_einvoice_inbound') !== false);
assert(strpos($inboundDeleteSql, 'BEFORE DELETE ON llx_dk_einvoice_inbound') !== false);
assert(strpos($inboundValidationUpdateSql, 'BEFORE UPDATE ON llx_dk_einvoice_inbound_validation') !== false);
assert(strpos($inboundValidationUpdateSql, 'evidence is immutable') !== false);
assert(strpos($inboundValidationDeleteSql, 'BEFORE DELETE ON llx_dk_einvoice_inbound_validation') !== false);
assert(strpos($inboundDraftUpdateSql, 'BEFORE UPDATE ON llx_dk_einvoice_inbound_draft') !== false);
assert(strpos($inboundDraftUpdateSql, 'provenance is immutable') !== false);
assert(strpos($inboundDraftDeleteSql, 'BEFORE DELETE ON llx_dk_einvoice_inbound_draft') !== false);

echo "DatabaseGuardInstaller tests passed\n";
