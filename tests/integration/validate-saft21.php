<?php

require_once __DIR__.'/../../dkmodul/class/Accounting/Canonical/AccountingDataProviderInterface.php';
require_once __DIR__.'/../../dkmodul/class/Accounting/Canonical/Transaction.php';
require_once __DIR__.'/../../dkmodul/class/Saft/V21/Saft21Exporter.php';
require_once __DIR__.'/../../dkmodul/class/Saft/SaftValidator.php';

if ($argc < 2) {
    fwrite(STDERR, "Usage: php validate-saft21.php /path/to/Danish_SAF-T_Financial_Schema_v_2_1.xsd\n");
    exit(2);
}

class DkSaftFixtureProvider implements DkAccountingDataProviderInterface
{
    public function getCompanyContext()
    {
        return array(
            'id' => '1',
            'name' => 'Dolibarr DK Test ApS',
            'registrationNumber' => '12345678',
            'currencyCode' => 'DKK',
            'address' => array(
                'streetName' => 'Testvej 1',
                'city' => 'Aarhus C',
                'postalCode' => '8000',
                'countryCode' => 'DK',
            ),
            'phone' => '70112233',
            'email' => 'test@example.invalid',
            'bankAccounts' => array(
                array(
                    'iban' => 'DK5000400440116243',
                    'bic' => 'DABADKKK',
                    'currencyCode' => 'DKK',
                    'accountId' => '5500',
                ),
            ),
        );
    }

    public function getAccounts($fromDate, $toDate)
    {
        return array(
            array(
                'accountCode' => '5500',
                'label' => 'Bank',
                'accountType' => 'ASSET',
                'openingBalance' => '0',
                'closingBalance' => '250',
                'standardAccountId' => '5500',
            ),
            array(
                'accountCode' => '1010',
                'label' => 'Salg af varer og ydelser',
                'accountType' => 'INCOME',
                'openingBalance' => '0',
                'closingBalance' => '-250',
                'standardAccountId' => '1010',
            ),
        );
    }

    public function getParties($fromDate, $toDate)
    {
        return array();
    }

    public function getTransactions($fromDate, $toDate)
    {
        return array(
            new DkCanonicalTransaction(array(
                'transactionId' => 'DK-1',
                'sourcePieceNumber' => 1,
                'journalCode' => 'OD',
                'journalDescription' => 'Diverse posteringer',
                'transactionDate' => '2026-09-18',
                'registrationDateTime' => '2026-09-18 10:00:00',
                'validatedAt' => '2026-09-18 10:01:00',
                'actor' => 'user:1',
                'sourceType' => 'dk_test',
                'documentRef' => 'BILAG-1',
                'description' => 'SAF-T testpostering',
                'lines' => array(
                    array(
                        'lineId' => '1',
                        'accountCode' => '5500',
                        'debit' => '250',
                        'credit' => '0',
                        'description' => 'Bank',
                        'sourceDocumentRef' => 'BILAG-1',
                    ),
                    array(
                        'lineId' => '2',
                        'accountCode' => '1010',
                        'debit' => '0',
                        'credit' => '250',
                        'description' => 'Salg',
                        'sourceDocumentRef' => 'BILAG-1',
                    ),
                ),
            )),
        );
    }
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
    'string(/saf:AuditFile/saf:GeneralLedgerEntries/saf:NumberOfEntries)' => '2',
);

foreach ($assertions as $query => $expected) {
    $actual = $xpath->evaluate($query);
    if ((string) $actual !== $expected) {
        fwrite(STDERR, "Unexpected SAF-T value for {$query}: {$actual}\n");
        exit(1);
    }
}

echo "Generated SAF-T 2.1 validates against official ERST XSD\n";
