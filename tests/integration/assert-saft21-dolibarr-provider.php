<?php

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);

require '/var/www/html/main.inc.php';
require '/var/www/html/custom/dkmodul/class/Accounting/DolibarrAccountingDataProvider.php';
require '/var/www/html/custom/dkmodul/class/Accounting/AccountMappingService.php';
require '/var/www/html/custom/dkmodul/class/Accounting/VatMappingService.php';
require '/var/www/html/custom/dkmodul/class/Saft/V21/Saft21Exporter.php';

$output = $argv[1] ?? '/tmp/dolibarr-dk-saft21.xml';

$provider = new DkDolibarrAccountingDataProvider($db, 1);
$accountMappings = new DkAccountMappingService($db);
$vatMappings = new DkVatMappingService($db);
$exporter = new DkSaft21Exporter($provider, $accountMappings, $vatMappings);

$xml = $exporter->export('2026-01-01', '2026-12-31', array(
    'createdDate' => '2026-09-18',
    'softwareCompanyName' => 'Dolibarr DK',
    'softwareId' => 'Dolibarr DK',
    'softwareVersion' => '0.1.0-dev',
    'taxEntity' => '12345678',
    'userId' => 'integration-test',
));

if (file_put_contents($output, $xml) === false) {
    fwrite(STDERR, "Unable to write generated SAF-T file\n");
    exit(1);
}

$dom = new DOMDocument();
if (!$dom->loadXML($xml)) {
    fwrite(STDERR, "Generated SAF-T is not well-formed XML\n");
    exit(1);
}

$xpath = new DOMXPath($dom);
$xpath->registerNamespace('saf', DkSaftSchemaRegistry::NAMESPACE_URI);

$checks = array(
    'string(/saf:AuditFile/saf:Header/saf:Company/saf:CVR)' => '12345678',
    'string(/saf:AuditFile/saf:Header/saf:DefaultCurrencyCode)' => 'DKK',
    'string(/saf:AuditFile/saf:MasterFiles/saf:GeneralLedgerAccounts/saf:VersionOfStandardAccount)' => '20260101',
    'string(/saf:AuditFile/saf:MasterFiles/saf:TaxTable/saf:TaxTableEntry/saf:TaxCodeDetails/saf:StandardTaxCode)' => 'S1',
    'string(/saf:AuditFile/saf:GeneralLedgerEntries/saf:NumberOfEntries)' => '5',
);

foreach ($checks as $query => $expected) {
    $actual = (string) $xpath->evaluate($query);
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected value for {$query}: {$actual}\n");
        exit(1);
    }
}

$taxInfoCount = (int) $xpath->evaluate(
    'count(/saf:AuditFile/saf:GeneralLedgerEntries/saf:Journal/saf:Transaction[saf:TransactionID="DK-990003"]/saf:Line[saf:AccountID="3999"]/saf:TaxInformation)'
);
if ($taxInfoCount !== 2) {
    fwrite(STDERR, "Expected two TaxInformation blocks on aggregated revenue line, got {$taxInfoCount}\n");
    exit(1);
}

$expectedTax = array(
    'DKT25A' => array('200.00000000', '50.00000000'),
    'DKT25B' => array('100.00000000', '25.00000000'),
);

foreach ($expectedTax as $localCode => $expected) {
    $base = (string) $xpath->evaluate(
        'string(//saf:Transaction[saf:TransactionID="DK-990003"]/saf:Line[saf:AccountID="3999"]/saf:TaxInformation[saf:TaxCode="'.$localCode.'"]/saf:TaxBase)'
    );
    $amount = (string) $xpath->evaluate(
        'string(//saf:Transaction[saf:TransactionID="DK-990003"]/saf:Line[saf:AccountID="3999"]/saf:TaxInformation[saf:TaxCode="'.$localCode.'"]/saf:TaxAmount/saf:Amount)'
    );
    $standardCode = (string) $xpath->evaluate(
        'string(//saf:Transaction[saf:TransactionID="DK-990003"]/saf:Line[saf:AccountID="3999"]/saf:TaxInformation[saf:TaxCode="'.$localCode.'"]/saf:StandardTaxCode)'
    );

    if ($base !== $expected[0] || $amount !== $expected[1] || $standardCode !== 'S1') {
        fwrite(STDERR, "Unexpected mapped VAT output for {$localCode}\n");
        exit(1);
    }
}

echo "Dolibarr provider generated strict SAF-T 2.1 candidate with VAT provenance\n";
