<?php

define('CSRFCHECK_WITH_TOKEN', 1);
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once __DIR__.'/../class/EInvoice/Inbound/InboundWorkflowService.php';
require_once __DIR__.'/../class/EInvoice/Inbound/InboundSupplierDraftService.php';
require_once __DIR__.'/../class/EInvoice/Inbound/InboundSupplierValidationService.php';
require_once __DIR__.'/../class/EInvoice/Inbound/InboundSupplierPostingService.php';

if (!$user->hasRight('dkmodul', 'inbound', 'read')) accessforbidden();

$entity = (int) $conf->entity;
$storageRoot = DOL_DATA_ROOT.'/dkmodul/inbound';
$workflow = new DkInboundWorkflowService($db);
$action = GETPOST('action', 'aZ09');

if ($action !== '') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') accessforbidden('Workflow actions require POST');
    $inboundRowId = GETPOSTINT('inbound_rowid');

    try {
        $row = $workflow->get($entity, $inboundRowId);
        if ($action === 'approve') {
            if (!$user->hasRight('dkmodul', 'inbound', 'approve') || $row['state'] !== 'validated') accessforbidden();
            $service = new DkInboundSupplierDraftService($db, $storageRoot);
            $supplier = $service->resolveSupplier($entity, $inboundRowId);
            $service->approveAndCreateDraft($entity, $inboundRowId, $supplier['rowid'], (int) $user->id, $user);
            setEventMessages('Supplier invoice draft approved and created', null, 'mesgs');
        } elseif ($action === 'validate') {
            if (!$user->hasRight('dkmodul', 'inbound', 'validate') || $row['state'] !== 'draft') accessforbidden();
            $service = new DkInboundSupplierValidationService($db, $storageRoot);
            $service->approveAndValidate($entity, $row['draftRowId'], (int) $user->id, $user);
            setEventMessages('Supplier invoice validated', null, 'mesgs');
        } elseif ($action === 'post') {
            if (!$user->hasRight('dkmodul', 'inbound', 'post') || $row['state'] !== 'supplier_validated') accessforbidden();
            $journalRowId = GETPOSTINT('journal_rowid');
            $service = new DkInboundSupplierPostingService($db);
            $result = $service->post($entity, $row['supplierValidationRowId'], $journalRowId, (int) $user->id, $user);
            setEventMessages('Supplier invoice posted as piece '.$result['pieceNum'], null, 'mesgs');
        } else {
            accessforbidden('Unknown workflow action');
        }
    } catch (Throwable $e) {
        setEventMessages($e->getMessage(), null, 'errors');
    }

    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}

$rows = array();
$journals = array();
try {
    $rows = $workflow->list($entity);
    if ($user->hasRight('dkmodul', 'inbound', 'post')) $journals = $workflow->purchaseJournals($entity);
} catch (Throwable $e) {
    setEventMessages($e->getMessage(), null, 'errors');
}

$title = 'Inbound OIOUBL workflow';
llxHeader('', $title);
print load_fiche_titre($title, '', 'fa-file-invoice');
print '<div class="opacitymedium marginbottomonly">Controlled progression from received OIOUBL to immutable bookkeeping. Each action is separately authorized and auditable.</div>';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>Received</td><td>Invoice</td><td>Supplier</td><td class="right">Total</td><td>Status</td><td>Next controlled action</td></tr>';
foreach ($rows as $row) {
    print '<tr class="oddeven">';
    print '<td>'.dol_escape_htmltag($row['receivedAt']).'<br><span class="opacitymedium">'.dol_escape_htmltag($row['channel']).'</span></td>';
    print '<td>'.dol_escape_htmltag($row['invoiceId'] ?: $row['providerMessageId']).'<br><span class="opacitymedium">'.dol_escape_htmltag($row['documentType'].' · '.$row['inboundUuid']).'</span></td>';
    print '<td>'.dol_escape_htmltag($row['supplierName'] ?: $row['senderEndpoint']).'</td>';
    print '<td class="right">'.($row['payableAmount'] === null ? '' : price($row['payableAmount']).' '.dol_escape_htmltag($row['currencyCode'])).'</td>';
    print '<td>'.dol_escape_htmltag($row['state']);
    if ($row['pieceNum'] > 0) print '<br><span class="opacitymedium">Piece '.((int) $row['pieceNum']).'</span>';
    print '</td><td>';
    if ($row['nextAction'] === 'approve' && $user->hasRight('dkmodul', 'inbound', 'approve')) {
        printActionForm('approve', $row['inboundRowId'], 'Approve and create draft');
    } elseif ($row['nextAction'] === 'validate' && $user->hasRight('dkmodul', 'inbound', 'validate')) {
        printActionForm('validate', $row['inboundRowId'], 'Validate supplier invoice');
    } elseif ($row['nextAction'] === 'post' && $user->hasRight('dkmodul', 'inbound', 'post')) {
        print '<form method="post" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
        print '<input type="hidden" name="token" value="'.newToken().'">';
        print '<input type="hidden" name="action" value="post"><input type="hidden" name="inbound_rowid" value="'.((int) $row['inboundRowId']).'">';
        print '<select class="flat minwidth150" name="journal_rowid" required><option value="">Purchase journal</option>';
        foreach ($journals as $journal) print '<option value="'.$journal['rowid'].'">'.dol_escape_htmltag($journal['code'].' — '.$journal['label']).'</option>';
        print '</select> <button class="button" type="submit">Post to ledger</button></form>';
    } else {
        print '<span class="opacitymedium">'.($row['nextAction'] === null ? 'Complete' : 'Awaiting authorized user').'</span>';
    }
    print '</td></tr>';
}
if (!$rows) print '<tr><td colspan="6"><span class="opacitymedium">No inbound OIOUBL invoices</span></td></tr>';
print '</table></div>';
llxFooter();
$db->close();

function printActionForm(string $action, int $inboundRowId, string $label): void
{
    print '<form method="post" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="'.dol_escape_htmltag($action).'">';
    print '<input type="hidden" name="inbound_rowid" value="'.$inboundRowId.'">';
    print '<button class="button" type="submit">'.dol_escape_htmltag($label).'</button></form>';
}
