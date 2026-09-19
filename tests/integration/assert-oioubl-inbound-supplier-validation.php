<?php

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);

require '/var/www/html/main.inc.php';
require '/var/www/html/custom/dkmodul/class/EInvoice/Inbound/InboundSupplierValidationService.php';

$resql = $db->query('SELECT rowid,supplier_invoice_rowid FROM '.$db->prefix().'dk_einvoice_inbound_draft WHERE entity=1 ORDER BY rowid DESC LIMIT 1');
$draft = $resql ? $db->fetch_object($resql) : false;
if (!$draft) throw new RuntimeException('Inbound supplier draft fixture is missing');

$validator = new User($db);
if ($validator->fetch(1) <= 0) throw new RuntimeException('Dolibarr validation user is missing');
$service = new DkInboundSupplierValidationService($db, '/var/www/documents/dkmodul/inbound');

try {
    $service->approveAndValidate(1, (int) $draft->rowid, 2, $validator);
    throw new RuntimeException('Mismatched validation actor was accepted');
} catch (InvalidArgumentException $expected) {
}

$before = $db->query('SELECT COUNT(*) AS total FROM '.$db->prefix().'accounting_bookkeeping WHERE entity=1 AND doc_type=\'supplier_invoice\' AND fk_doc='.(int) $draft->supplier_invoice_rowid);
$beforeCount = (int) $db->fetch_object($before)->total;
$created = $service->approveAndValidate(1, (int) $draft->rowid, 1, $validator);
$reused = $service->approveAndValidate(1, (int) $draft->rowid, 1, $validator);
if (!$reused['reused'] || $created['rowid'] !== $reused['rowid']) throw new RuntimeException('Supplier invoice validation is not idempotent');

$resql = $db->query('SELECT fk_statut,fk_user_valid,date_valid FROM '.$db->prefix().'facture_fourn WHERE entity=1 AND rowid='.(int) $draft->supplier_invoice_rowid);
$invoice = $resql ? $db->fetch_object($resql) : false;
if (!$invoice || (int) $invoice->fk_statut !== 1 || (int) $invoice->fk_user_valid !== 1 || !$invoice->date_valid) {
    throw new RuntimeException('Supplier invoice was not validated by the explicit actor');
}
$after = $db->query('SELECT COUNT(*) AS total FROM '.$db->prefix().'accounting_bookkeeping WHERE entity=1 AND doc_type=\'supplier_invoice\' AND fk_doc='.(int) $draft->supplier_invoice_rowid);
if ((int) $db->fetch_object($after)->total !== $beforeCount) throw new RuntimeException('Supplier invoice validation unexpectedly transferred bookkeeping');

echo 'Inbound supplier invoice explicitly validated without ledger transfer: '.$draft->supplier_invoice_rowid."\n";
