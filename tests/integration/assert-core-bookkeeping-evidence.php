<?php

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);

require '/var/www/html/main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

$user = new User($db);
if ($user->fetch(1) <= 0 || empty($user->id)) {
    throw new RuntimeException('Unable to load integration-test Dolibarr user');
}

$resql = $db->query(
    'SELECT COALESCE(MAX(piece_num),0) AS max_piece FROM '.$db->prefix().'accounting_bookkeeping WHERE entity=1'
);
$beforeAllocation = $db->fetch_object($resql);
$previousPieceNum = $beforeAllocation ? (int) $beforeAllocation->max_piece : 0;

$allocator = new BookKeeping($db);
$pieceNum = (int) $allocator->getNextNumMvt();
if ($pieceNum <= 0 || ($previousPieceNum > 0 && $pieceNum !== $previousPieceNum + 1)) {
    throw new RuntimeException('Unable to allocate evidence piece number');
}

$expectedDate = '2026-09-18';
$docRef = 'DK-EVIDENCE-ACC-001-005';
$lineIds = array();
$lines = array(
    array('account' => '1000', 'accountLabel' => 'Cash', 'operation' => 'Core evidence debit', 'debit' => 175.25, 'credit' => 0.0),
    array('account' => '3000', 'accountLabel' => 'Revenue', 'operation' => 'Core evidence credit', 'debit' => 0.0, 'credit' => 175.25),
);

foreach ($lines as $index => $line) {
    $bookkeeping = new BookKeeping($db);
    $bookkeeping->doc_date = dol_mktime(0, 0, 0, 9, 18, 2026);
    $bookkeeping->doc_type = 'dk_core_evidence';
    $bookkeeping->doc_ref = $docRef;
    $bookkeeping->fk_doc = 500001;
    $bookkeeping->fk_docdet = $index + 1;
    $bookkeeping->thirdparty_code = '';
    $bookkeeping->subledger_account = '';
    $bookkeeping->subledger_label = '';
    $bookkeeping->numero_compte = $line['account'];
    $bookkeeping->label_compte = $line['accountLabel'];
    $bookkeeping->label_operation = $line['operation'];
    $bookkeeping->debit = $line['debit'];
    $bookkeeping->credit = $line['credit'];
    $bookkeeping->montant = $line['debit'] > 0 ? $line['debit'] : -1 * $line['credit'];
    $bookkeeping->sens = $line['debit'] > 0 ? 'D' : 'C';
    $bookkeeping->import_key = '';
    $bookkeeping->code_journal = 'OD';
    $bookkeeping->journal_label = 'Miscellaneous operations';
    $bookkeeping->piece_num = $pieceNum;
    $bookkeeping->ref = 'DK-EVIDENCE-'.$pieceNum;
    $bookkeeping->entity = 1;

    $rowId = $bookkeeping->createStd($user, 0, '');
    if ($rowId <= 0) {
        throw new RuntimeException('Dolibarr API rejected evidence line: '.implode('; ', $bookkeeping->errors));
    }
    $lineIds[] = (int) $rowId;
}

$resql = $db->query(
    'SELECT rowid,DATE_FORMAT(doc_date,\'%Y-%m-%d\') AS doc_date,doc_ref,label_operation,'
    .' debit,credit,piece_num,ref,fk_user_author,date_creation'
    .' FROM '.$db->prefix().'accounting_bookkeeping'
    .' WHERE entity=1 AND piece_num='.$pieceNum.' ORDER BY rowid ASC'
);
$created = array();
while ($row = $db->fetch_object($resql)) {
    $created[] = $row;
}
if (count($created) !== 2) {
    throw new RuntimeException('Dolibarr API did not create the balanced evidence movement');
}

$totalDebit = 0.0;
$totalCredit = 0.0;
foreach ($created as $index => $row) {
    if ((string) $row->doc_date !== $expectedDate
        || (string) $row->doc_ref !== $docRef
        || (string) $row->label_operation !== $lines[$index]['operation']
        || (int) $row->piece_num !== $pieceNum
        || trim((string) $row->ref) === ''
        || (int) $row->fk_user_author !== (int) $user->id
        || empty($row->date_creation)) {
        throw new RuntimeException('Core bookkeeping evidence fields were not persisted as supplied');
    }
    $totalDebit += (float) $row->debit;
    $totalCredit += (float) $row->credit;
}
if (abs($totalDebit - 175.25) > 0.00001 || abs($totalCredit - 175.25) > 0.00001) {
    throw new RuntimeException('Core bookkeeping evidence amounts are not balanced');
}

$idList = implode(',', array_map('intval', $lineIds));
$resql = $db->query(
    'SELECT COUNT(*) AS nb FROM '.$db->prefix().'dk_bookkeeping_origin'
    .' WHERE bookkeeping_rowid IN ('.$idList.') AND piece_num='.$pieceNum
    .' AND fk_user_author='.((int) $user->id)
    ." AND origin_type='insert' AND database_user <> '' AND CHAR_LENGTH(content_hash)=64"
);
$origin = $db->fetch_object($resql);
if (!$origin || (int) $origin->nb !== 2) {
    throw new RuntimeException('Database provenance does not identify the evidence movement and actor');
}

$resql = $db->query(
    'SELECT COUNT(*) AS nb FROM '.$db->prefix().'dk_audit_event'
    ." WHERE entity=1 AND event_type='bookkeeping.created'"
    ." AND object_type='accounting_bookkeeping' AND object_id IN (".$idList.')'
    .' AND actor_id='.((int) $user->id)
);
$audit = $db->fetch_object($resql);
if (!$audit || (int) $audit->nb !== 2) {
    throw new RuntimeException('Application audit does not identify the evidence movement and actor');
}

if (!$db->query(
    'UPDATE '.$db->prefix().'accounting_bookkeeping SET date_validated=NOW()'
    .' WHERE entity=1 AND piece_num='.$pieceNum
)) {
    throw new RuntimeException('Unable to validate evidence movement: '.$db->lasterror());
}

$attempt = new BookKeeping($db);
if ($attempt->fetch($lineIds[0]) <= 0) {
    throw new RuntimeException('Unable to fetch evidence row through Dolibarr API');
}
$attempt->debit = 999.99;
if ($attempt->update($user, 0, '') >= 0) {
    throw new RuntimeException('Normal Dolibarr API update changed validated bookkeeping');
}

$attemptWithoutTriggers = new BookKeeping($db);
if ($attemptWithoutTriggers->fetch($lineIds[0]) <= 0) {
    throw new RuntimeException('Unable to refetch evidence row through Dolibarr API');
}
$attemptWithoutTriggers->debit = 888.88;
if ($attemptWithoutTriggers->update($user, 1, '') >= 0) {
    throw new RuntimeException('Dolibarr API update with triggers disabled bypassed the database guard');
}

$resql = $db->query(
    'SELECT debit,date_validated FROM '.$db->prefix().'accounting_bookkeeping'
    .' WHERE rowid='.$lineIds[0]
);
$unchanged = $db->fetch_object($resql);
if (!$unchanged || abs((float) $unchanged->debit - 175.25) > 0.00001 || empty($unchanged->date_validated)) {
    throw new RuntimeException('Validated bookkeeping changed after rejected API updates');
}

echo 'DK-ACC-001-005 evidence passed for piece '.$pieceNum
    ."; API updates with and without application triggers were rejected\n";
