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

echo "DatabaseGuardInstaller tests passed\n";
