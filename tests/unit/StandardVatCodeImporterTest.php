<?php

require_once __DIR__.'/../../dkmodul/class/Accounting/StandardVatCodeImporter.php';

$importer = new DkStandardVatCodeImporter();
$json = file_get_contents(__DIR__.'/../fixtures/standard-vat-minimal.json');
$parsed = $importer->parseJson($json, '20260101');

assert($parsed['version'] === '20260101');
assert($parsed['validFrom'] === '2025-12-01');
assert(count($parsed['codes']) === 2);

$byCode = array();
foreach ($parsed['codes'] as $code) {
    $byCode[$code['legacyCode']] = $code;
}

assert($byCode['S1']['newCode'] === 'S01');
assert($byCode['S1']['legacyLabelCode'] === 'S-DK-25');
assert($byCode['S1']['newLabelCode'] === 'S_DK_25');
assert($byCode['S1']['taxPercentage'] === '25');
assert($byCode['S%']['taxPercentage'] === null);

echo "Standard VAT code importer tests passed\n";
