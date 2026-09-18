<?php

require_once __DIR__.'/../../dkmodul/class/Accounting/StandardVatCodeImporter.php';

if ($argc < 2) {
    fwrite(STDERR, "Usage: php validate-official-vat-list.php /path/to/vat.json\n");
    exit(2);
}

$json = file_get_contents($argv[1]);
if ($json === false) {
    fwrite(STDERR, "Unable to read official VAT JSON\n");
    exit(1);
}

$importer = new DkStandardVatCodeImporter();
$parsed = $importer->parseJson($json, '20260101');

if ($parsed['validFrom'] !== '2025-12-01') {
    fwrite(STDERR, "Unexpected official VAT valid-from date: ".$parsed['validFrom']."\n");
    exit(1);
}

$byCode = array();
foreach ($parsed['codes'] as $code) {
    $byCode[$code['legacyCode']] = $code;
}

if (!isset($byCode['S1']) || $byCode['S1']['newCode'] !== 'S01') {
    fwrite(STDERR, "Official VAT list no longer maps S1 to S01 as expected\n");
    exit(1);
}

if (count($parsed['codes']) < 10) {
    fwrite(STDERR, "Official VAT list unexpectedly contains fewer than 10 codes\n");
    exit(1);
}

echo "Official ERST VAT list parsed: ".count($parsed['codes'])." codes; valid from ".$parsed['validFrom']."\n";
