<?php

require_once __DIR__.'/../fixtures/SaftFixtureProvider.php';
require_once __DIR__.'/../../dkmodul/class/Saft/V21/Saft21Exporter.php';

$provider = new DkSaftFixtureProvider();
$exporter = new DkSaft21Exporter($provider);
$xml = $exporter->export('2026-01-01', '2026-12-31', array(
    'createdDate' => '2026-09-18',
    'softwareCompanyName' => 'Dolibarr DK',
    'softwareId' => 'Dolibarr DK',
    'softwareVersion' => '0.1.0-dev',
    'taxEntity' => '12345678',
));

$dom = new DOMDocument();
assert($dom->loadXML($xml) === true);

$xpath = new DOMXPath($dom);
$xpath->registerNamespace('saf', DkSaftSchemaRegistry::NAMESPACE_URI);

assert($xpath->evaluate('string(/saf:AuditFile/saf:Header/saf:AuditFileVersion)') === '2.1');
assert($xpath->evaluate('string(/saf:AuditFile/saf:Header/saf:Company/saf:CVR)') === '12345678');
assert($xpath->evaluate('string(/saf:AuditFile/saf:MasterFiles/saf:GeneralLedgerAccounts/saf:VersionOfStandardAccount)') === '20260101');
assert($xpath->evaluate('string(/saf:AuditFile/saf:GeneralLedgerEntries/saf:NumberOfEntries)') === '3');
assert($xpath->evaluate('string(/saf:AuditFile/saf:GeneralLedgerEntries/saf:TotalDebit)') === '312.50000000');
assert($xpath->evaluate('string(/saf:AuditFile/saf:GeneralLedgerEntries/saf:TotalCredit)') === '312.50000000');
assert($xpath->evaluate('string(/saf:AuditFile/saf:GeneralLedgerEntries/saf:Journal/saf:Description)') === 'Diverse posteringer');
assert($xpath->evaluate('string(/saf:AuditFile/saf:GeneralLedgerEntries/saf:Journal/saf:Transaction/saf:Line[saf:AccountID="1010"]/saf:TaxInformation/saf:TaxCode)') === 'Salg25');
assert($xpath->evaluate('string(/saf:AuditFile/saf:GeneralLedgerEntries/saf:Journal/saf:Transaction/saf:Line[saf:AccountID="1010"]/saf:TaxInformation/saf:StandardTaxCode)') === 'S1');
assert($xpath->evaluate('string(/saf:AuditFile/saf:GeneralLedgerEntries/saf:Journal/saf:Transaction/saf:Line[saf:AccountID="1010"]/saf:TaxInformation/saf:TaxAmount/saf:Amount)') === '62.50000000');

echo "SAF-T 2.1 exporter unit tests passed\n";
