<?php

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);

require '/var/www/html/main.inc.php';
require '/var/www/html/custom/dkmodul/class/EInvoice/Inbound/InboundSupplierPostingService.php';

$resql = $db->query('SELECT rowid,supplier_invoice_rowid FROM '.$db->prefix().'dk_einvoice_inbound_supplier_validation WHERE entity=1 ORDER BY rowid DESC LIMIT 1');
$validation = $resql ? $db->fetch_object($resql) : false;
$resql = $db->query('SELECT rowid FROM '.$db->prefix().'accounting_journal WHERE entity=1 AND nature=3 AND active=1 ORDER BY rowid LIMIT 1');
$journal = $resql ? $db->fetch_object($resql) : false;
$resql = $db->query("SELECT rowid FROM ".$db->prefix()."accounting_account WHERE entity=1 AND account_number='4000' AND active=1 ORDER BY rowid LIMIT 1");
$expenseAccount = $resql ? $db->fetch_object($resql) : false;
if (!$validation || !$journal || !$expenseAccount) throw new RuntimeException('Inbound posting fixture is incomplete');

$poster = new User($db);
if ($poster->fetch(1) <= 0) throw new RuntimeException('Dolibarr posting user is missing');
$service = new DkInboundSupplierPostingService($db);

try {
    $service->post(1, (int) $validation->rowid, (int) $journal->rowid, 2, $poster);
    throw new RuntimeException('Mismatched posting actor was accepted');
} catch (InvalidArgumentException $expected) {
}

$db->query('UPDATE '.$db->prefix().'facture_fourn_det SET fk_code_ventilation=0 WHERE fk_facture_fourn='.(int) $validation->supplier_invoice_rowid);
try {
    $service->post(1, (int) $validation->rowid, (int) $journal->rowid, 1, $poster);
    throw new RuntimeException('Missing purchase-account mapping was accepted');
} catch (RuntimeException $expected) {
}
$resql = $db->query("SELECT COUNT(*) AS total FROM ".$db->prefix()."accounting_bookkeeping WHERE entity=1 AND doc_type='supplier_invoice' AND fk_doc=".(int) $validation->supplier_invoice_rowid);
if ((int) $db->fetch_object($resql)->total !== 0) throw new RuntimeException('Failed posting left partial bookkeeping rows');
$db->query('UPDATE '.$db->prefix().'facture_fourn_det SET fk_code_ventilation='.(int) $expenseAccount->rowid.' WHERE fk_facture_fourn='.(int) $validation->supplier_invoice_rowid);

$created = $service->post(1, (int) $validation->rowid, (int) $journal->rowid, 1, $poster);
$reused = $service->post(1, (int) $validation->rowid, (int) $journal->rowid, 1, $poster);
if (!$reused['reused'] || $created['rowid'] !== $reused['rowid'] || $created['pieceNum'] !== $reused['pieceNum']) {
    throw new RuntimeException('Inbound supplier posting is not idempotent');
}

$sql = 'SELECT COUNT(*) AS line_count,COUNT(DISTINCT piece_num) AS piece_count,SUM(debit) AS debit_total,SUM(credit) AS credit_total,';
$sql .= ' SUM(date_validated IS NOT NULL) AS locked_count,GROUP_CONCAT(numero_compte ORDER BY numero_compte) AS accounts';
$sql .= ' FROM '.$db->prefix()."accounting_bookkeeping WHERE entity=1 AND doc_type='supplier_invoice' AND fk_doc=".(int) $validation->supplier_invoice_rowid;
$resql = $db->query($sql);
$movement = $resql ? $db->fetch_object($resql) : false;
if (!$movement || (int) $movement->line_count !== 3 || (int) $movement->piece_count !== 1 || (int) $movement->locked_count !== 3
    || abs((float) $movement->debit_total - 312.5) > 0.001 || abs((float) $movement->credit_total - 312.5) > 0.001
    || (string) $movement->accounts !== '2000,4000,4450') {
    throw new RuntimeException('Unexpected inbound supplier bookkeeping movement');
}

echo 'Validated inbound supplier invoice posted as immutable balanced movement: '.$created['pieceNum']."\n";
