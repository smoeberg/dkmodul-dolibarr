<?php

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);

require '/var/www/html/main.inc.php';
require '/var/www/html/custom/dkmodul/class/Saft/V21/Saft21Importer.php';
require '/var/www/html/custom/dkmodul/class/Saft/V21/Saft21ImportStagingService.php';
require '/var/www/html/custom/dkmodul/class/Saft/V21/Saft21ImportAnalyzer.php';

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

echo "SAF-T 2.1 staged safely: ".$result['transactionCount']." transactions / "
    .$result['lineCount']." lines; all account mappings resolved; duplicate blocked\n";
