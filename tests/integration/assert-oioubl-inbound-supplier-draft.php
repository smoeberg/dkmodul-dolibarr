<?php

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);

require '/var/www/html/main.inc.php';
require '/var/www/html/custom/dkmodul/class/EInvoice/Inbound/InboundSupplierDraftService.php';

$resql = $db->query("SELECT rowid FROM ".$db->prefix()."dk_einvoice_inbound_validation WHERE entity=1 AND event_type='validated' ORDER BY rowid DESC LIMIT 1");
$validation = $resql ? $db->fetch_object($resql) : false;
if (!$validation) throw new RuntimeException('Validated inbound fixture is missing');
$resql = $db->query('SELECT inbound_rowid FROM '.$db->prefix().'dk_einvoice_inbound_validation WHERE rowid='.(int) $validation->rowid);
$inbound = $resql ? $db->fetch_object($resql) : false;

$service = new DkInboundSupplierDraftService($db, '/var/www/documents/dkmodul/inbound');
$supplier = $service->resolveSupplier(1, (int) $inbound->inbound_rowid);
$before = $db->query("SELECT COUNT(*) AS total FROM ".$db->prefix()."accounting_bookkeeping WHERE entity=1 AND doc_ref='DKVAT-1'");
$beforeCount = (int) $db->fetch_object($before)->total;
$created = $service->approveAndCreateDraft(1, (int) $inbound->inbound_rowid, $supplier['rowid'], (int) $user->id, $user);
$reused = $service->approveAndCreateDraft(1, (int) $inbound->inbound_rowid, $supplier['rowid'], (int) $user->id, $user);
if (!$reused['reused'] || $created['supplierInvoiceRowId'] !== $reused['supplierInvoiceRowId']) throw new RuntimeException('Inbound draft creation is not idempotent');

$resql = $db->query('SELECT fk_statut,total_ht,total_tva,total_ttc,ref_supplier FROM '.$db->prefix().'facture_fourn WHERE rowid='.(int) $created['supplierInvoiceRowId']);
$draft = $resql ? $db->fetch_object($resql) : false;
if (!$draft || (int) $draft->fk_statut !== 0 || $draft->ref_supplier !== 'DKVAT-1'
    || abs((float) $draft->total_ht - 250.0) > 0.01 || abs((float) $draft->total_ttc - 312.5) > 0.01) {
    throw new RuntimeException('Unexpected Dolibarr supplier invoice draft');
}
$after = $db->query("SELECT COUNT(*) AS total FROM ".$db->prefix()."accounting_bookkeeping WHERE entity=1 AND doc_ref='DKVAT-1'");
if ((int) $db->fetch_object($after)->total !== $beforeCount) throw new RuntimeException('Draft creation unexpectedly posted bookkeeping');

echo 'Validated inbound OIOUBL approved into supplier invoice draft: '.$created['supplierInvoiceRowId']."\n";
