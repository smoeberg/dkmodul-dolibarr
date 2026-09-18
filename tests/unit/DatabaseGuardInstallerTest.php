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

echo "DatabaseGuardInstaller tests passed\n";
