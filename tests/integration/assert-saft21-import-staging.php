<?php

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);

require '/var/www/html/main.inc.php';
require '/var/www/html/custom/dkmodul/class/Saft/V21/Saft21Importer.php';
require '/var/www/html/custom/dkmodul/class/Saft/V21/Saft21ImportStagingService.php';
require '/var/www/html/custom/dkmodul/class/Saft/V21/Saft21ImportAnalyzer.php';
require '/var/www/html/custom/dkmodul/class/Saft/V21/Saft21ImportApplyService.php';
require '/var/www/html/custom/dkmodul/class/Accounting/DolibarrAccountingDataProvider.php';
require '/var/www/html/custom/dkmodul/class/Accounting/AccountMappingService.php';
require '/var/www/html/custom/dkmodul/class/Accounting/VatMappingService.php';
require '/var/www/html/custom/dkmodul/class/Saft/V21/Saft21Exporter.php';

if ($argc < 3) {
    fwrite(STDERR, "Usage: php assert-saft21-import-staging.php /path/file.xml /path/schema.xsd\n");
    exit(2);
}

$xml = file_get_contents($argv[1]);
if ($xml === false) {
    fwrite(STDERR, "Unable to read generated SAF-T\n");
    exit(1);
}

$importer = new DkSaft21Importer();
$package = $importer->importXml($xml, $argv[2]);

$staging = new DkSaft21ImportStagingService($db);
$result = $staging->stage(1, $package, 1);

if ($result['status'] !== 'staged' || $result['lineCount'] !== $package->lineCount()) {
    fwrite(STDERR, "Unexpected SAF-T staging result\n");
    exit(1);
}

$analyzer = new DkSaft21ImportAnalyzer($db);
$analysis = $analyzer->analyze(1, $result['id'], '2026-09-18');

if (!$analysis['canApply']) {
    fwrite(STDERR, "Staged SAF-T mapping analysis failed: ".implode('; ', $analysis['errors'])."\n");
    exit(1);
}

$duplicateBlocked = false;
try {
    $staging->stage(1, $package, 1);
} catch (InvalidArgumentException $e) {
    $duplicateBlocked = str_contains($e->getMessage(), 'already');
} catch (RuntimeException $e) {
    $duplicateBlocked = str_contains($e->getMessage(), 'already');
}

if (!$duplicateBlocked) {
    fwrite(STDERR, "Duplicate SAF-T source hash was not blocked\n");
    exit(1);
}

$apply = new DkSaft21ImportApplyService($db);
$applied = $apply->apply(1, $result['id'], $user);

if ($applied['status'] !== 'applied'
    || $applied['transactionCount'] !== $result['transactionCount']
    || $applied['lineCount'] !== $result['lineCount']) {
    fwrite(STDERR, "Unexpected SAF-T Apply result\n");
    exit(1);
}

$lockedCount = (int) $db->getValue(
    "SELECT COUNT(*) FROM ".$db->prefix()."accounting_bookkeeping"
    ." WHERE entity=1 AND doc_type='saft_import' AND fk_doc=".((int) $result['id'])
    ." AND date_validated IS NOT NULL"
);
if ($lockedCount !== $result['lineCount']) {
    fwrite(STDERR, "Applied SAF-T bookkeeping rows are not all locked\n");
    exit(1);
}

$linkedCount = (int) $db->getValue(
    "SELECT COUNT(*) FROM ".$db->prefix()."dk_saft_import_line WHERE bookkeeping_rowid IS NOT NULL"
);
if ($linkedCount !== $result['lineCount']) {
    fwrite(STDERR, "Applied SAF-T lines are missing bookkeeping provenance links\n");
    exit(1);
}

$originCount = (int) $db->getValue(
    "SELECT COUNT(*) FROM ".$db->prefix()."dk_bookkeeping_origin o"
    ." INNER JOIN ".$db->prefix()."accounting_bookkeeping b ON b.rowid=o.bookkeeping_rowid"
    ." WHERE b.entity=1 AND b.doc_type='saft_import' AND b.fk_doc=".((int) $result['id'])
);
if ($originCount !== $result['lineCount']) {
    fwrite(STDERR, "Applied SAF-T rows are missing database provenance\n");
    exit(1);
}

$secondApplyBlocked = false;
try {
    $apply->apply(1, $result['id'], $user);
} catch (RuntimeException $e) {
    $secondApplyBlocked = str_contains($e->getMessage(), 'Only staged');
}
if (!$secondApplyBlocked) {
    fwrite(STDERR, "Second SAF-T Apply was not blocked\n");
    exit(1);
}

$provider = new DkDolibarrAccountingDataProvider($db, 1);
$roundTripTransactions = $provider->getTransactions('2026-01-01', '2026-12-31');
$foundImportedTax = false;

foreach ($roundTripTransactions as $transaction) {
    if ($transaction->sourceType !== 'saft_import') {
        continue;
    }

    foreach ($transaction->lines as $line) {
        foreach ($line->taxInformation as $taxInformation) {
            if ($taxInformation->standardTaxCode === 'S1') {
                $foundImportedTax = true;
            }
        }
    }
}

if (!$foundImportedTax) {
    fwrite(STDERR, "Imported StandardTaxCode was not preserved through Dolibarr provider\n");
    exit(1);
}

$accountMappings = new DkAccountMappingService($db);
$vatMappings = new DkVatMappingService($db);
$exporter = new DkSaft21Exporter($provider, $accountMappings, $vatMappings);
$roundTripXml = $exporter->export('2026-01-01', '2026-12-31', array(
    'createdDate' => '2026-09-18',
    'softwareCompanyName' => 'Dolibarr DK',
    'softwareId' => 'Dolibarr DK',
    'softwareVersion' => 'import-roundtrip-test',
    'taxEntity' => '12345678',
    'userId' => 'integration-test',
));

$validator = new DkSaftValidator();
$validator->validateXml($roundTripXml, $argv[2]);

echo "SAF-T 2.1 import Apply passed: ".$result['transactionCount']." transactions / "
    .$result['lineCount']." lines; locked, provenance-linked, duplicate/reapply blocked, roundtrip XSD valid\n";
