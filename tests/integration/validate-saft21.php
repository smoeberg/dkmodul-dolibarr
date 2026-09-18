<?php

require_once __DIR__.'/../fixtures/SaftFixtureProvider.php';
require_once __DIR__.'/../../dkmodul/class/Saft/V21/Saft21Exporter.php';
require_once __DIR__.'/../../dkmodul/class/Saft/SaftValidator.php';

if ($argc < 2) {
    fwrite(STDERR, "Usage: php validate-saft21.php /path/to/Danish_SAF-T_Financial_Schema_v_2_1.xsd\n");
    exit(2);
}

$provider = new DkSaftFixtureProvider();
$exporter = new DkSaft21Exporter($provider);
$xml = $exporter->export('2026-01-01', '2026-12-31', array(
    'createdDate' => '2026-09-18',
    'softwareCompanyName' => 'Dolibarr DK',
    'softwareId' => 'Dolibarr DK',
    'softwareVersion' => '0.1.0-dev',
    'taxEntity' => '12345678',
    'userId' => 'ci',
));

$validator = new DkSaftValidator();
$validator->validateXml($xml, $argv[1]);

$dom = new DOMDocument();
$dom->loadXML($xml);
$xpath = new DOMXPath($dom);
$xpath->registerNamespace('saf', DkSaftSchemaRegistry::NAMESPACE_URI);

$assertions = array(
    'string(/saf:AuditFile/saf:Header/saf:AuditFileVersion)' => '2.1',
    'string(/saf:AuditFile/saf:MasterFiles/saf:GeneralLedgerAccounts/saf:VersionOfStandardAccount)' => '20260101',
    'string(/saf:AuditFile/saf:GeneralLedgerEntries/saf:NumberOfEntries)' => '3',
    'string(/saf:AuditFile/saf:GeneralLedgerEntries/saf:Journal/saf:Transaction/saf:Line[saf:AccountID="1010"]/saf:TaxInformation/saf:TaxCode)' => 'Salg25',
    'string(/saf:AuditFile/saf:GeneralLedgerEntries/saf:Journal/saf:Transaction/saf:Line[saf:AccountID="1010"]/saf:TaxInformation/saf:StandardTaxCode)' => 'S1',
    'string(/saf:AuditFile/saf:GeneralLedgerEntries/saf:Journal/saf:Transaction/saf:Line[saf:AccountID="1010"]/saf:TaxInformation/saf:TaxBase)' => '250.00000000',
);

foreach ($assertions as $query => $expected) {
    $actual = $xpath->evaluate($query);
    if ((string) $actual !== $expected) {
        fwrite(STDERR, "Unexpected SAF-T value for {$query}: {$actual}\n");
        exit(1);
    }
}

echo "Generated SAF-T 2.1 validates against official ERST XSD\n";
