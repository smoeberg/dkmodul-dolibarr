<?php

require_once __DIR__.'/../../dkmodul/class/Accounting/Canonical/AccountingDataProviderInterface.php';
require_once __DIR__.'/../../dkmodul/class/Accounting/Canonical/Transaction.php';

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
                'closingBalance' => '-200',
                'standardAccountId' => '1010',
            ),
            array(
                'accountCode' => '2610',
                'label' => 'Salgsmoms',
                'accountType' => 'LIABILITY',
                'openingBalance' => '0',
                'closingBalance' => '-50',
                'standardAccountId' => '2610',
            ),
        );
    }

    public function getParties($fromDate, $toDate)
    {
        return array();
    }

    public function getTaxCodes($fromDate, $toDate)
    {
        return array(
            array(
                'taxCode' => 'Salg25',
                'taxType' => 'VAT',
                'description' => 'Salgsmoms (udgående moms)',
                'standardTaxCode' => 'S1',
                'standardTaxCodeDescription' => 'Momspligtige salg (DK), 25% moms',
                'effectiveDate' => '2026-01-01',
                'expirationDate' => '2099-12-31',
                'taxPercentage' => '25',
                'countryCode' => 'DK',
            ),
        );
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
                        'credit' => '200',
                        'description' => 'Salg',
                        'sourceDocumentRef' => 'BILAG-1',
                        'taxComponents' => array(
                            array(
                                'taxCode' => 'Salg25',
                                'taxPercentage' => '25',
                                'taxBase' => '200',
                                'taxAmount' => '50',
                                'taxBaseDescription' => 'Salg af varer og ydelser',
                            ),
                        ),
                    ),
                    array(
                        'lineId' => '3',
                        'accountCode' => '2610',
                        'debit' => '0',
                        'credit' => '50',
                        'description' => 'Salgsmoms',
                        'sourceDocumentRef' => 'BILAG-1',
                    ),
                ),
            )),
        );
    }
}


class DkSaftFixtureVatMappingService
{
    public function resolve($entity, $sourceTaxCode, $date)
    {
        if ((int) $entity !== 1 || $sourceTaxCode !== 'Salg25' || $date !== '2026-09-18') {
            return null;
        }

        return array(
            'standardVersion' => DkSaftSchemaRegistry::STANDARD_VAT_VERSION,
            'standardTaxCode' => 'S1',
            'effectiveDate' => '2025-12-01',
            'expirationDate' => null,
            'description' => 'Momspligtige salg (DK), 25% moms',
            'taxPercentage' => '25',
            'countryCode' => 'DK',
        );
    }
}
