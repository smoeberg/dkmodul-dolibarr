<?php

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);

require '/var/www/html/main.inc.php';
require '/var/www/html/custom/dkmodul/class/EInvoice/Inbound/InboundInvoiceStagingService.php';
require '/var/www/html/custom/dkmodul/class/EInvoice/Inbound/InboundSupplierDraftService.php';
require '/var/www/html/custom/dkmodul/class/EInvoice/Inbound/InboundSupplierValidationService.php';
require '/var/www/html/custom/dkmodul/class/EInvoice/Inbound/InboundSupplierPostingService.php';

$xml = file_get_contents('/tmp/DKCR-1.xml');
$schematron = file_get_contents('/tmp/OIOUBL_CreditNote_Schematron_Result.xml');
if ($xml === false || $schematron === false) throw new RuntimeException('Inbound OIOUBL credit-note fixtures are missing');

$user = new User($db);
if ($user->fetch(1) <= 0) throw new RuntimeException('Dolibarr credit-note actor is missing');
$staging = new DkInboundInvoiceStagingService($db, '/var/www/documents/dkmodul/inbound');
$staged = $staging->stage(1, 'nemhandel', 'provider-credit-note-1', 'GLN', '5790001968502', $xml, 1);
$validated = $staging->validate(1, $staged['rowid'], '/tmp/oioubl-schema/maindoc/UBL-CreditNote-2.1.xsd', $schematron, 1);
if ($validated['state'] !== 'validated' || ($validated['metadata']['documentType'] ?? '') !== 'CreditNote'
    || ($validated['metadata']['creditedInvoiceId'] ?? '') !== 'DKVAT-1') {
    throw new RuntimeException('Inbound OIOUBL credit note did not retain its controlled identity');
}

$draftService = new DkInboundSupplierDraftService($db, '/var/www/documents/dkmodul/inbound');
$supplier = $draftService->resolveSupplier(1, $staged['rowid']);
$created = $draftService->approveAndCreateDraft(1, $staged['rowid'], $supplier['rowid'], 1, $user);
$reused = $draftService->approveAndCreateDraft(1, $staged['rowid'], $supplier['rowid'], 1, $user);
if (!$reused['reused'] || $created['supplierInvoiceRowId'] !== $reused['supplierInvoiceRowId']) {
    throw new RuntimeException('Inbound supplier credit-note creation is not idempotent');
}

$sql = 'SELECT c.type,c.fk_statut,c.total_ht,c.total_tva,c.total_ttc,c.fk_facture_source,c.ref_supplier,o.ref_supplier AS original_ref';
$sql .= ' FROM '.$db->prefix().'facture_fourn c LEFT JOIN '.$db->prefix().'facture_fourn o ON o.rowid=c.fk_facture_source';
$sql .= ' WHERE c.rowid='.(int) $created['supplierInvoiceRowId'];
$resql = $db->query($sql);
$credit = $resql ? $db->fetch_object($resql) : false;
if (!$credit || (int) $credit->type !== FactureFournisseur::TYPE_CREDIT_NOTE || (int) $credit->fk_statut !== 0
    || (string) $credit->ref_supplier !== 'DKCR-1' || (string) $credit->original_ref !== 'DKVAT-1'
    || abs((float) $credit->total_ht + 250.0) > 0.01 || abs((float) $credit->total_ttc + 312.5) > 0.01) {
    throw new RuntimeException('Unexpected native Dolibarr supplier credit-note draft');
}

$resql = $db->query('SELECT rowid FROM '.$db->prefix().'dk_einvoice_inbound_draft WHERE entity=1 AND supplier_invoice_rowid='.(int) $created['supplierInvoiceRowId']);
$draftEvidence = $resql ? $db->fetch_object($resql) : false;
if (!$draftEvidence) throw new RuntimeException('Credit-note draft evidence is missing');
$validation = new DkInboundSupplierValidationService($db, '/var/www/documents/dkmodul/inbound');
$validatedSupplier = $validation->approveAndValidate(1, (int) $draftEvidence->rowid, 1, $user);

$resql = $db->query("SELECT rowid FROM ".$db->prefix()."accounting_account WHERE entity=1 AND account_number='4000' AND active=1 ORDER BY rowid LIMIT 1");
$expense = $resql ? $db->fetch_object($resql) : false;
$resql = $db->query('SELECT rowid FROM '.$db->prefix().'accounting_journal WHERE entity=1 AND nature=3 AND active=1 ORDER BY rowid LIMIT 1');
$journal = $resql ? $db->fetch_object($resql) : false;
if (!$expense || !$journal) throw new RuntimeException('Credit-note posting mappings are missing');
$db->query('UPDATE '.$db->prefix().'facture_fourn_det SET fk_code_ventilation='.(int) $expense->rowid.",vat_src_code='DKBUY25' WHERE fk_facture_fourn=".(int) $created['supplierInvoiceRowId']);

$posting = new DkInboundSupplierPostingService($db);
$posted = $posting->post(1, $validatedSupplier['rowid'], (int) $journal->rowid, 1, $user);
$postedAgain = $posting->post(1, $validatedSupplier['rowid'], (int) $journal->rowid, 1, $user);
if (!$postedAgain['reused'] || $postedAgain['pieceNum'] !== $posted['pieceNum']) throw new RuntimeException('Credit-note posting is not idempotent');

$sql = 'SELECT COUNT(*) AS line_count,SUM(debit) AS debit_total,SUM(credit) AS credit_total,';
$sql .= " SUM(numero_compte='2000' AND debit=312.5) AS payable_debit,";
$sql .= " SUM(numero_compte='4000' AND credit=250) AS purchase_credit,";
$sql .= " SUM(numero_compte='4450' AND credit=62.5) AS vat_credit";
$sql .= ' FROM '.$db->prefix()."accounting_bookkeeping WHERE entity=1 AND doc_type='supplier_invoice' AND fk_doc=".(int) $created['supplierInvoiceRowId'];
$resql = $db->query($sql);
$movement = $resql ? $db->fetch_object($resql) : false;
if (!$movement || (int) $movement->line_count !== 3 || abs((float) $movement->debit_total - 312.5) > 0.001
    || abs((float) $movement->credit_total - 312.5) > 0.001 || (int) $movement->payable_debit !== 1
    || (int) $movement->purchase_credit !== 1 || (int) $movement->vat_credit !== 1) {
    throw new RuntimeException('Supplier credit note did not produce an exact reversed balanced movement');
}

echo 'Inbound OIOUBL credit note posted as controlled reversal: '.$posted['pieceNum']."\n";
