<?php

require_once __DIR__.'/../../dkmodul/class/Accounting/StandardVatImporter.php';

if ($argc < 2) {
    fwrite(STDERR, "Usage: php validate-standard-vat.php /path/to/2026-01-01-Momskoder-Bruttoliste.json\n");
    exit(2);
}

$json = file_get_contents($argv[1]);
if ($json === false) {
    fwrite(STDERR, "Unable to read official VAT JSON\n");
    exit(1);
}

$importer = new DkStandardVatImporter();
$parsed = $importer->parseJson($json, '20260101');

if ($parsed['validFrom'] !== '2025-12-01') {
    fwrite(STDERR, "Unexpected official VAT valid-from date: ".$parsed['validFrom']."\n");
    exit(1);
}

$byCode = array();
foreach ($parsed['codes'] as $code) {
    $byCode[$code['taxCode']] = $code;
}

if (!isset($byCode['S1'])) {
    fwrite(STDERR, "Official VAT list no longer contains S1\n");
    exit(1);
}

if (($byCode['S1']['nextTaxCode'] ?? null) !== 'S01') {
    fwrite(STDERR, "Unexpected next VAT code for S1\n");
    exit(1);
}

if (($byCode['S1']['taxPercentage'] ?? null) !== '25.000000') {
    fwrite(STDERR, "Unexpected S1 VAT percentage\n");
    exit(1);
}

echo "Official 2026 Danish VAT JSON parsed successfully: ".count($parsed['codes'])." codes\n";
