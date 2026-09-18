<?php

require_once __DIR__.'/../../dkmodul/class/Accounting/StandardVatImporter.php';

$importer = new DkStandardVatImporter();
$json = file_get_contents(__DIR__.'/../fixtures/standard-vat-minimal.json');
$parsed = $importer->parseJson($json, '20260101');

assert($parsed['releaseVersion'] === '20260101');
assert($parsed['validFrom'] === '2025-12-01');
assert(strlen($parsed['sourceHash']) === 64);
assert(count($parsed['codes']) === 2);

$s1 = $parsed['codes'][0];
assert($s1['taxCode'] === 'S1');
assert($s1['legacyTaxCode'] === 'S1');
assert($s1['nextTaxCode'] === 'S01');
assert($s1['designation'] === 'S-DK-25');
assert($s1['nextDesignation'] === 'S_DK_25');
assert($s1['taxPercentage'] === '25.000000');
assert($s1['groupName'] === 'Salg');

$outside = $parsed['codes'][1];
assert($outside['taxCode'] === 'S%');
assert($outside['taxPercentage'] === null);

echo "Standard VAT importer tests passed\n";
