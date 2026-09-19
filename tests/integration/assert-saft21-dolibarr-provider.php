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
$projectionSets = array(
    array(array($provider->getCompanyContext()), DkCanonicalCompanyContext::class, 'company context'),
    array($provider->getAccounts('2026-01-01', '2026-12-31'), DkCanonicalAccount::class, 'account'),
    array($provider->getParties('2026-01-01', '2026-12-31'), DkCanonicalParty::class, 'party'),
    array($provider->getTaxCodes('2026-01-01', '2026-12-31'), DkCanonicalTaxCode::class, 'tax code'),
    array($provider->getTransactions('2026-01-01', '2026-12-31'), DkCanonicalTransaction::class, 'transaction'),
);
foreach ($projectionSets as $set) {
    $expectedClass = $set[1];
    foreach ($set[0] as $record) {
        if (!$record instanceof $expectedClass) {
            fwrite(STDERR, 'Dolibarr provider returned a non-canonical '.$set[2]."\n");
            exit(1);
        }
    }
}

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
    'string(/saf:AuditFile/saf:MasterFiles/saf:TaxTable/saf:TaxTableEntry/saf:TaxCodeDetails[saf:TaxCode="DKTEST25"]/saf:StandardTaxCode)' => 'S1',
    'string(/saf:AuditFile/saf:GeneralLedgerEntries/saf:NumberOfEntries)' => '13',
    'string(/saf:AuditFile/saf:GeneralLedgerEntries/saf:Journal/saf:Transaction[saf:TransactionID="DK-990003"]/saf:Line[saf:AccountID="3000"]/saf:TaxInformation/saf:TaxCode)' => 'DKTEST25',
    'string(/saf:AuditFile/saf:GeneralLedgerEntries/saf:Journal/saf:Transaction[saf:TransactionID="DK-990003"]/saf:Line[saf:AccountID="3000"]/saf:TaxInformation/saf:StandardTaxCode)' => 'S1',
    'string(/saf:AuditFile/saf:GeneralLedgerEntries/saf:Journal/saf:Transaction[saf:TransactionID="DK-990003"]/saf:Line[saf:AccountID="3000"]/saf:TaxInformation/saf:TaxBase)' => '250.00000000',
    'string(/saf:AuditFile/saf:GeneralLedgerEntries/saf:Journal/saf:Transaction[saf:TransactionID="DK-990003"]/saf:Line[saf:AccountID="3000"]/saf:TaxInformation/saf:TaxAmount/saf:Amount)' => '62.50000000',
    'string(/saf:AuditFile/saf:GeneralLedgerEntries/saf:Journal/saf:Transaction[saf:TransactionID="DK-990004"]/saf:Line[saf:AccountID="4000"]/saf:TaxInformation/saf:TaxCode)' => 'DKBUY25',
    'string(/saf:AuditFile/saf:GeneralLedgerEntries/saf:Journal/saf:Transaction[saf:TransactionID="DK-990004"]/saf:Line[saf:AccountID="4000"]/saf:TaxInformation/saf:TaxBase)' => '200.00000000',
    'string(/saf:AuditFile/saf:GeneralLedgerEntries/saf:Journal/saf:Transaction[saf:TransactionID="DK-990004"]/saf:Line[saf:AccountID="4000"]/saf:TaxInformation/saf:TaxAmount/saf:Amount)' => '50.00000000',
    'string(/saf:AuditFile/saf:GeneralLedgerEntries/saf:Journal/saf:Transaction[saf:TransactionID="DK-990005"]/saf:Line[saf:AccountID="3000"]/saf:TaxInformation/saf:TaxCode)' => 'DKSALE0',
    'string(/saf:AuditFile/saf:GeneralLedgerEntries/saf:Journal/saf:Transaction[saf:TransactionID="DK-990005"]/saf:Line[saf:AccountID="3000"]/saf:TaxInformation/saf:TaxPercentage)' => '0.00000000',
    'string(/saf:AuditFile/saf:GeneralLedgerEntries/saf:Journal/saf:Transaction[saf:TransactionID="DK-990005"]/saf:Line[saf:AccountID="3000"]/saf:TaxInformation/saf:TaxAmount/saf:Amount)' => '0.00000000',
    'string(/saf:AuditFile/saf:GeneralLedgerEntries/saf:Journal/saf:Transaction[saf:TransactionID="DK-990006"]/saf:Line[saf:AccountID="3000"]/saf:TaxInformation/saf:TaxCode)' => 'DKTEST25',
    'string(/saf:AuditFile/saf:GeneralLedgerEntries/saf:Journal/saf:Transaction[saf:TransactionID="DK-990006"]/saf:Line[saf:AccountID="3000"]/saf:TaxInformation/saf:TaxBase)' => '-100.00000000',
    'string(/saf:AuditFile/saf:GeneralLedgerEntries/saf:Journal/saf:Transaction[saf:TransactionID="DK-990006"]/saf:Line[saf:AccountID="3000"]/saf:TaxInformation/saf:TaxAmount/saf:Amount)' => '-25.00000000',
);

foreach ($checks as $query => $expected) {
    $actual = (string) $xpath->evaluate($query);
    if ($actual !== $expected) {
        fwrite(STDERR, "Unexpected value for {$query}: {$actual}\n");
        exit(1);
    }
}

echo "Dolibarr provider generated strict SAF-T 2.1 candidate\n";
