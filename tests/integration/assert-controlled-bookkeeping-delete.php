<?php

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);

require '/var/www/html/main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/dkmodul/class/Accounting/BookkeepingDeleteService.php';
require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/dkmodul/class/Audit/AuditLedger.php';

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

function insertBookkeeping($db, $piece, $importKey, $journal = 'OD', $validated = false)
{
    $prefix = $db->prefix();
    $dateValidated = $validated ? 'NOW()' : 'NULL';
    $sql = "INSERT INTO {$prefix}accounting_bookkeeping
(entity,ref,piece_num,doc_date,doc_type,doc_ref,numero_compte,label_operation,debit,credit,
 fk_user_author,date_creation,code_journal,journal_label,date_validated,import_key)
VALUES
(1,'DK-DEL-".$piece."',".$piece.",'2026-10-01','dk_delete_test','DEL-".$piece."','1000','delete test',10,0,1,NOW(),'".$db->escape($journal)."','Delete test',".$dateValidated.",'".$db->escape($importKey)."'),
(1,'DK-DEL-".$piece."',".$piece.",'2026-10-01','dk_delete_test','DEL-".$piece."','3000','delete test',0,10,1,NOW(),'".$db->escape($journal)."','Delete test',".$dateValidated.",'".$db->escape($importKey)."')";
    if (!$db->query($sql)) {
        throw new RuntimeException($db->lasterror());
    }
}

$prefix = $db->prefix();
$piece = 990100 + random_int(1, 800);

/* Controlled delete by import key: per-row audit + authenticated actor. */
$importKey = 'DKDEL-'.$piece;
insertBookkeeping($db, $piece, $importKey);
$service = new DkBookkeepingDeleteService($db);
if ($service->deleteByImportkey(1, $importKey, $user) !== 2) {
    throw new RuntimeException('Controlled import-key delete did not remove both rows');
}
if (sqlCount($db, "SELECT COUNT(*) AS nb FROM {$prefix}accounting_bookkeeping WHERE import_key='".$db->escape($importKey)."'") !== 0) {
    throw new RuntimeException('Controlled import-key delete left bookkeeping rows behind');
}
if (sqlCount($db, "SELECT COUNT(*) AS nb FROM {$prefix}dk_audit_event WHERE entity=1 AND event_type='bookkeeping.deleted' AND actor_id=".$user->id." AND object_type='accounting_bookkeeping'") < 2) {
    throw new RuntimeException('Controlled delete did not append per-row audit evidence');
}

/* Controlled delete by year + journal. */
$piece2 = $piece + 1;
$yearJournalKey = 'DKYJ-'.$piece2;
insertBookkeeping($db, $piece2, $yearJournalKey, 'DDEL');
if ($service->deleteByYearAndJournal(1, 2026, 'DDEL', $user) < 2) {
    throw new RuntimeException('Controlled year+journal delete did not remove the target rows');
}
if (sqlCount($db, "SELECT COUNT(*) AS nb FROM {$prefix}accounting_bookkeeping WHERE import_key='".$db->escape($yearJournalKey)."'") !== 0) {
    throw new RuntimeException('Controlled year+journal delete left rows behind');
}

/* Controlled delete by movement number. */
$piece3 = $piece + 2;
$mvtKey = 'DKMVT-'.$piece3;
insertBookkeeping($db, $piece3, $mvtKey, 'OD');
if ($service->deleteMvtNum(1, $piece3, $user) !== 2) {
    throw new RuntimeException('Controlled movement-number delete did not remove both rows');
}
if (sqlCount($db, "SELECT COUNT(*) AS nb FROM {$prefix}accounting_bookkeeping WHERE piece_num=".$piece3) !== 0) {
    throw new RuntimeException('Controlled movement-number delete left rows behind');
}

/* Raw direct SQL is rejected for unvalidated rows. */
$piece4 = $piece + 3;
$rawKey = 'DKRAW-'.$piece4;
insertBookkeeping($db, $piece4, $rawKey);
expectSqlFailure($db, "DELETE FROM {$prefix}accounting_bookkeeping WHERE import_key='".$db->escape($rawKey)."'");
if (sqlCount($db, "SELECT COUNT(*) AS nb FROM {$prefix}accounting_bookkeeping WHERE import_key='".$db->escape($rawKey)."'") !== 2) {
    throw new RuntimeException('Raw direct SQL delete bypassed the DB guard');
}

/* Trigger-bypass / validated-row case: validated rows remain immutable. */
$piece5 = $piece + 4;
$validatedKey = 'DKVAL-'.$piece5;
insertBookkeeping($db, $piece5, $validatedKey, 'OD', true);
expectSqlFailure($db, "DELETE FROM {$prefix}accounting_bookkeeping WHERE import_key='".$db->escape($validatedKey)."'");
if (sqlCount($db, "SELECT COUNT(*) AS nb FROM {$prefix}accounting_bookkeeping WHERE import_key='".$db->escape($validatedKey)."'") !== 2) {
    throw new RuntimeException('Validated rows were deleted');
}

/* Forged validation cannot authorize deletion: the guard remains fail-closed. */
$piece6 = $piece + 5;
$forgedKey = 'DKFORGED-'.$piece6;
insertBookkeeping($db, $piece6, $forgedKey);
if (!$db->query("UPDATE {$prefix}accounting_bookkeeping SET date_validated=NOW() WHERE import_key='".$db->escape($forgedKey)."'")) {
    throw new RuntimeException('Unable to set validation fixture');
}
expectSqlFailure($db, "DELETE FROM {$prefix}accounting_bookkeeping WHERE import_key='".$db->escape($forgedKey)."'");
if (sqlCount($db, "SELECT COUNT(*) AS nb FROM {$prefix}accounting_bookkeeping WHERE import_key='".$db->escape($forgedKey)."'") !== 2) {
    throw new RuntimeException('Forged validation state authorized a delete');
}

/* Audit failure must roll back the mutation. */
class FailingDkAuditLedger
{
    public function append($entity, $eventType, $objectType, $objectId, $actorId, array $payload = array(), array $metadata = array())
    {
        throw new RuntimeException('forced audit append failure');
    }
}
$piece7 = $piece + 6;
$auditFailureKey = 'DKAUDITFAIL-'.$piece7;
insertBookkeeping($db, $piece7, $auditFailureKey);
$failedService = new DkBookkeepingDeleteService($db, new FailingDkAuditLedger());
try {
    $failedService->deleteByImportkey(1, $auditFailureKey, $user);
    throw new RuntimeException('Audit failure did not reject controlled delete');
} catch (RuntimeException $e) {
    if (strpos($e->getMessage(), 'Audit failure did not reject') !== false) {
        throw $e;
    }
}
if (sqlCount($db, "SELECT COUNT(*) AS nb FROM {$prefix}accounting_bookkeeping WHERE import_key='".$db->escape($auditFailureKey)."'") !== 2) {
    throw new RuntimeException('Audit failure did not roll back bookkeeping mutation');
}

/* The append-only audit ledger remains verifiable. */
$ledger = new DkAuditLedger($db);
if (!$ledger->verifyChain(1)) {
    throw new RuntimeException('Audit chain failed after controlled delete test');
}

echo "Controlled bookkeeping delete evidence passed
";
