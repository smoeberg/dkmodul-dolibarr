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
assert(count($package->accounts) === 3);
assert(count($package->transactions) === 1);
assert($package->lineCount() === 3);
assert(DkCanonicalDecimal::equals($package->totalDebit(), '312.5'));
assert(DkCanonicalDecimal::equals($package->totalCredit(), '312.5'));
assert($package->transactions[0]->transactionId === 'DK-1');
assert($package->transactions[0]->lines[0]->accountCode === '5500');
assert(count($package->transactions[0]->lines[1]->taxInformation) === 1);
assert($package->transactions[0]->lines[1]->taxInformation[0]->taxCode === 'Salg25');
assert($package->transactions[0]->lines[1]->taxInformation[0]->standardTaxCode === 'S1');

$broken = preg_replace(
    '/<NumberOfEntries>3<\/NumberOfEntries>/',
    '<NumberOfEntries>4</NumberOfEntries>',
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

$missingActorXml = preg_replace('/<SourceID>[^<]+<\\/SourceID>/', '', $xml, 1);
$failed = false;
try {
    $importer->importXml($missingActorXml, 'not-used.xsd');
} catch (InvalidArgumentException $e) {
    $failed = str_contains($e->getMessage(), 'SourceID/actor identity');
}
assert($failed === true);

$badTaxXml = preg_replace('/<TaxCode>Salg25<\\/TaxCode>/', '<TaxCode>UNKNOWN<\\/TaxCode>', $xml, 1);
$failed = false;
try {
    $importer->importXml($badTaxXml, 'not-used.xsd');
} catch (InvalidArgumentException $e) {
    $failed = str_contains($e->getMessage(), 'undeclared tax code');
}
assert($failed === true);

$negativeAmountXml = preg_replace('/<Amount>100<\\/Amount>/', '<Amount>-100<\\/Amount>', $xml, 1);
$failed = false;
try {
    $importer->importXml($negativeAmountXml, 'not-used.xsd');
} catch (InvalidArgumentException $e) {
    $failed = str_contains($e->getMessage(), 'negative debit/credit');
}
assert($failed === true);

$outOfPeriodXml = preg_replace('/<TransactionDate>2026-09-18<\\/TransactionDate>/', '<TransactionDate>2027-01-01<\\/TransactionDate>', $xml, 1);
$failed = false;
try {
    $importer->importXml($outOfPeriodXml, 'not-used.xsd');
} catch (InvalidArgumentException $e) {
    $failed = str_contains($e->getMessage(), 'outside the declared selection period');
}
assert($failed === true);

echo "SAF-T 2.1 importer unit tests passed\n";
