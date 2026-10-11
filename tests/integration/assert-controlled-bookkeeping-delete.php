<?php

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);

require '/var/www/html/main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/dkmodul/class/Accounting/BookkeepingDeleteService.php';
require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';

$user = new User($db);
if ($user->fetch(1) <= 0 || empty($user->id)) {
    throw new RuntimeException('Unable to load integration-test user');
}

function sqlCount($db, $sql)
{
    $resql = $db->query($sql);
    if (!$resql) {
        throw new RuntimeException($db->lasterror());
    }
    $row = $db->fetch_object($resql);
    return (int) $row->nb;
}

function expectSqlFailure($db, $sql)
{
    if ($db->query($sql)) {
        throw new RuntimeException('Expected SQL failure but statement succeeded');
    }
}

$prefix = $db->prefix();
$piece = 990100 + random_int(1, 800);
$ref = 'DK-DEL-'.$piece;

$sql = "INSERT INTO {$prefix}accounting_bookkeeping
(entity,ref,piece_num,doc_date,doc_type,doc_ref,numero_compte,label_operation,debit,credit,
 fk_user_author,date_creation,code_journal,journal_label,date_validated,import_key)
VALUES
(1,'".$db->escape($ref)."',".$piece.",'2026-10-01','dk_delete_test','DEL-1','1000','delete test',10,0,1,NOW(),'OD','Delete test',NULL,'DKDEL-".$piece."'),
(1,'".$db->escape($ref)."',".$piece.",'2026-10-01','dk_delete_test','DEL-1','3000','delete test',0,10,1,NOW(),'OD','Delete test',NULL,'DKDEL-".$piece."')";
if (!$db->query($sql)) {
    throw new RuntimeException($db->lasterror());
}

$service = new DkBookkeepingDeleteService($db);
$importKey = 'DKDEL-'.$piece;
if ($service->deleteByImportkey(1, $importKey, $user) !== 2) {
    throw new RuntimeException('Controlled import-key delete did not remove both rows');
}

if (sqlCount($db, "SELECT COUNT(*) AS nb FROM {$prefix}accounting_bookkeeping WHERE import_key='".$db->escape($importKey)."'") !== 0) {
    throw new RuntimeException('Controlled delete left bookkeeping rows behind');
}

if (sqlCount($db, "SELECT COUNT(*) AS nb FROM {$prefix}dk_audit_event WHERE entity=1 AND event_type='bookkeeping.deleted' AND actor_id=".$user->id) < 2) {
    throw new RuntimeException('Controlled delete did not append per-row audit evidence');
}

$piece2 = $piece + 1;
$ref2 = 'DK-DEL-'.$piece2;
$sql = "INSERT INTO {$prefix}accounting_bookkeeping
(entity,ref,piece_num,doc_date,doc_type,doc_ref,numero_compte,label_operation,debit,credit,
 fk_user_author,date_creation,code_journal,journal_label,date_validated,import_key)
VALUES
(1,'".$db->escape($ref2)."',".$piece2.",'2026-10-01','dk_delete_test','DEL-2','1000','raw delete test',5,0,1,NOW(),'OD','Delete test',NULL,'DKRAW-".$piece2."')";
if (!$db->query($sql)) {
    throw new RuntimeException($db->lasterror());
}

$rawImport = 'DKRAW-'.$piece2;
expectSqlFailure($db, "DELETE FROM {$prefix}accounting_bookkeeping WHERE import_key='".$db->escape($rawImport)."'");
if (sqlCount($db, "SELECT COUNT(*) AS nb FROM {$prefix}accounting_bookkeeping WHERE import_key='".$db->escape($rawImport)."'") !== 1) {
    throw new RuntimeException('Raw direct SQL delete bypassed the DB guard');
}

$piece3 = $piece + 2;
$ref3 = 'DK-DEL-'.$piece3;
$sql = "INSERT INTO {$prefix}accounting_bookkeeping
(entity,ref,piece_num,doc_date,doc_type,doc_ref,numero_compte,label_operation,debit,credit,
 fk_user_author,date_creation,code_journal,journal_label,date_validated,import_key)
VALUES
(1,'".$db->escape($ref3)."',".$piece3.",'2026-10-01','dk_delete_test','DEL-3','1000','validated',5,0,1,NOW(),'OD','Delete test',NOW(),'DKVAL-".$piece3."')";
if (!$db->query($sql)) {
    throw new RuntimeException($db->lasterror());
}
expectSqlFailure($db, "DELETE FROM {$prefix}accounting_bookkeeping WHERE import_key='DKVAL-".$piece3."'");
if (sqlCount($db, "SELECT COUNT(*) AS nb FROM {$prefix}accounting_bookkeeping WHERE import_key='DKVAL-".$piece3."'") !== 1) {
    throw new RuntimeException('Validated row was deleted');
}

if (!(new DkAuditLedger($db))->verifyChain(1)) {
    throw new RuntimeException('Audit chain failed after controlled delete test');
}

echo "Controlled bookkeeping delete evidence passed\n";
