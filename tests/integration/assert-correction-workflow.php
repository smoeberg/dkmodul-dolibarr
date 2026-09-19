<?php

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);

require '/var/www/html/main.inc.php';
require '/var/www/html/custom/dkmodul/class/Accounting/CorrectionService.php';

$user = new User($db);
if ($user->fetch(1) <= 0 || empty($user->id)) {
    throw new RuntimeException('Unable to load integration-test Dolibarr user');
}

$originalPieceNum = 990002;
$originalBefore = array();
$resql = $db->query(
    'SELECT rowid,debit,credit,date_validated FROM '.$db->prefix().'accounting_bookkeeping'
    .' WHERE entity=1 AND piece_num='.$originalPieceNum.' ORDER BY rowid ASC'
);
while ($row = $db->fetch_object($resql)) {
    $originalBefore[(int) $row->rowid] = array(
        'debit' => (string) $row->debit,
        'credit' => (string) $row->credit,
        'date_validated' => (string) $row->date_validated,
    );
}
if (count($originalBefore) !== 2) {
    throw new RuntimeException('Correction fixture must contain two original rows');
}

$service = new DkCorrectionService($db);
$result = $service->reverse(1, $originalPieceNum, 'Integration correction', $user);

if ($result['originalPieceNum'] !== $originalPieceNum || $result['lineCount'] !== 2) {
    throw new RuntimeException('Unexpected correction result');
}

$reversalPieceNum = (int) $result['reversalPieceNum'];
$resql = $db->query(
    'SELECT COUNT(*) AS line_count,COALESCE(SUM(debit),0) AS debit,COALESCE(SUM(credit),0) AS credit,'
    .' SUM(CASE WHEN date_validated IS NOT NULL THEN 1 ELSE 0 END) AS validated_count'
    .' FROM '.$db->prefix().'accounting_bookkeeping'
    .' WHERE entity=1 AND piece_num='.$reversalPieceNum." AND doc_type='dk_correction'"
);
$reversal = $db->fetch_object($resql);
if (!$reversal
    || (int) $reversal->line_count !== 2
    || abs((float) $reversal->debit - 250.0) > 0.00001
    || abs((float) $reversal->credit - 250.0) > 0.00001
    || (int) $reversal->validated_count !== 2) {
    throw new RuntimeException('Reversal is not balanced and validated');
}

$resql = $db->query(
    'SELECT rowid,debit,credit,date_validated FROM '.$db->prefix().'accounting_bookkeeping'
    .' WHERE entity=1 AND piece_num='.$originalPieceNum.' ORDER BY rowid ASC'
);
while ($row = $db->fetch_object($resql)) {
    $before = $originalBefore[(int) $row->rowid] ?? null;
    if (!$before
        || (string) $row->debit !== $before['debit']
        || (string) $row->credit !== $before['credit']
        || (string) $row->date_validated !== $before['date_validated']) {
        throw new RuntimeException('Original bookkeeping row changed during correction');
    }
}

$relationCount = 0;
$resql = $db->query(
    'SELECT COUNT(*) AS nb FROM '.$db->prefix().'dk_correction'
    .' WHERE entity=1 AND original_piece_num='.$originalPieceNum
    .' AND correction_piece_num='.$reversalPieceNum
    ." AND relation_type='reversal' AND reason='Integration correction'"
);
if ($row = $db->fetch_object($resql)) {
    $relationCount = (int) $row->nb;
}
if ($relationCount !== 1) {
    throw new RuntimeException('Correction relation was not persisted');
}

$replacementPieceNum = 990003;
$service->record(
    1,
    $originalPieceNum,
    $replacementPieceNum,
    'replacement',
    'Integration replacement',
    (int) $user->id
);
$resql = $db->query(
    'SELECT COUNT(*) AS nb FROM '.$db->prefix().'dk_correction'
    .' WHERE entity=1 AND original_piece_num='.$originalPieceNum
    .' AND correction_piece_num='.$replacementPieceNum
    ." AND relation_type='replacement' AND reason='Integration replacement'"
);
$replacement = $db->fetch_object($resql);
if (!$replacement || (int) $replacement->nb !== 1) {
    throw new RuntimeException('Replacement relation was not persisted');
}

$auditCount = 0;
$resql = $db->query(
    'SELECT COUNT(*) AS nb FROM '.$db->prefix().'dk_audit_event'
    ." WHERE entity=1 AND event_type='bookkeeping.correction.recorded'"
    ." AND object_type='accounting_piece' AND object_id=".$originalPieceNum
);
if ($row = $db->fetch_object($resql)) {
    $auditCount = (int) $row->nb;
}
if ($auditCount !== 2) {
    throw new RuntimeException('Correction audit events were not persisted');
}

$duplicateBlocked = false;
try {
    $service->reverse(1, $originalPieceNum, 'Second reversal', $user);
} catch (RuntimeException $e) {
    $duplicateBlocked = str_contains($e->getMessage(), 'already has a reversal');
}
if (!$duplicateBlocked) {
    throw new RuntimeException('A second reversal of the same piece was not blocked');
}

echo 'Correction workflow passed: original '.$originalPieceNum
    .' -> reversal '.$reversalPieceNum.' -> replacement '.$replacementPieceNum
    ."; immutable, balanced, linked and audited\n";
