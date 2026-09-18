<?php

require_once __DIR__.'/../fixtures/SaftFixtureProvider.php';
require_once __DIR__.'/../../dkmodul/class/Saft/V21/Saft21Exporter.php';
require_once __DIR__.'/../../dkmodul/class/Saft/V21/Saft21Importer.php';

class DkAcceptAllSaftValidator
{
    public function validateXml($xml, $xsdPath)
    {
        return true;
    }
}

$provider = new DkSaftFixtureProvider();
$exporter = new DkSaft21Exporter($provider);
$xml = $exporter->export('2026-01-01', '2026-12-31', array(
    'createdDate' => '2026-09-18',
    'softwareCompanyName' => 'Dolibarr DK',
    'softwareId' => 'Dolibarr DK',
    'softwareVersion' => 'import-test',
    'taxEntity' => '12345678',
));

$importer = new DkSaft21Importer(new DkAcceptAllSaftValidator());
$package = $importer->importXml($xml, 'not-used.xsd');

assert($package->header['auditFileVersion'] === '2.1');
assert($package->header['auditFileCountry'] === 'DK');
assert($package->header['companyRegistrationNumber'] === '12345678');
assert(count($package->accounts) === 2);
assert(count($package->transactions) === 1);
assert($package->lineCount() === 2);
assert(DkCanonicalDecimal::equals($package->totalDebit(), '250'));
assert(DkCanonicalDecimal::equals($package->totalCredit(), '250'));
assert($package->transactions[0]->transactionId === 'DK-1');
assert($package->transactions[0]->lines[0]->accountCode === '5500');

$broken = preg_replace(
    '/<NumberOfEntries>2<\/NumberOfEntries>/',
    '<NumberOfEntries>3</NumberOfEntries>',
    $xml,
    1
);

$failed = false;
try {
    $importer->importXml($broken, 'not-used.xsd');
} catch (InvalidArgumentException $e) {
    $failed = str_contains($e->getMessage(), 'NumberOfEntries');
}
assert($failed === true);

echo "SAF-T 2.1 importer unit tests passed\n";
