<?php

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);

require '/var/www/html/main.inc.php';
require '/var/www/html/custom/dkmodul/class/Documents/DocumentArchiveService.php';

$resql = $db->query("SELECT rowid FROM ".$db->prefix()."accounting_bookkeeping WHERE entity=1 AND piece_num=990001");
$row = $resql ? $db->fetch_object($resql) : false;
if (!$row) {
    throw new RuntimeException('Document test bookkeeping row is missing');
}

$source = '/tmp/dk-document-fixture.pdf';
$bytes = "%PDF-1.4\nDolibarr DK immutable evidence\n%%EOF\n";
if (file_put_contents($source, $bytes) !== strlen($bytes)) {
    throw new RuntimeException('Unable to create document fixture');
}

$storageRoot = '/var/www/documents/dkmodul/archive';
$service = new DkDocumentArchiveService($db, $storageRoot);
$archived = $service->archive(
    1,
    (int) $row->rowid,
    'bookkeeping_evidence',
    990001,
    $source,
    'bilag-990001.pdf',
    '2031-12-31',
    1,
    'application/pdf'
);

if ($archived['retainUntil'] !== '2036-12-31') {
    throw new RuntimeException('Unexpected document retention deadline');
}
if (!$service->verify(1, (int) $archived['rowid'])) {
    throw new RuntimeException('Archived document failed content verification');
}

$resql = $db->query("SELECT event_type,payload_json FROM ".$db->prefix()."dk_audit_event WHERE object_type='dk_document_archive' AND object_id=".((int) $archived['rowid']));
$audit = $resql ? $db->fetch_object($resql) : false;
if (!$audit || $audit->event_type !== 'document.archived' || strpos($audit->payload_json, $archived['contentHash']) === false) {
    throw new RuntimeException('Document archive audit evidence is missing');
}

echo 'Digital document archived and verified: '.$archived['rowid']."\n";
