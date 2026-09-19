<?php

require_once __DIR__.'/../../dkmodul/class/Accounting/Canonical/AccountingDataProviderInterface.php';
require_once __DIR__.'/../../dkmodul/class/Accounting/Canonical/CompanyContext.php';
require_once __DIR__.'/../../dkmodul/class/Accounting/Canonical/Account.php';
require_once __DIR__.'/../../dkmodul/class/Accounting/Canonical/TaxCode.php';
require_once __DIR__.'/../../dkmodul/class/Accounting/Canonical/Transaction.php';

class DkSaftFixtureProvider implements DkAccountingDataProviderInterface
{
    public function getCompanyContext()
    {
        return new DkCanonicalCompanyContext(array(
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
        ));
    }

    public function getAccounts($fromDate, $toDate)
    {
        return array(
            new DkCanonicalAccount(array(
                'accountCode' => '5500',
                'label' => 'Bank',
                'accountType' => 'ASSET',
                'openingBalance' => '0',
                'closingBalance' => '312.5',
                'standardAccountId' => '5500',
            )),
            new DkCanonicalAccount(array(
                'accountCode' => '1010',
                'label' => 'Salg af varer og ydelser',
                'accountType' => 'INCOME',
                'openingBalance' => '0',
                'closingBalance' => '-250',
                'standardAccountId' => '1010',
            )),
            new DkCanonicalAccount(array(
                'accountCode' => '2600',
                'label' => 'Salgsmoms',
                'accountType' => 'LIABILITY',
                'openingBalance' => '0',
                'closingBalance' => '-62.5',
                'standardAccountId' => '2600',
            )),
        );
    }

    public function getParties($fromDate, $toDate)
    {
        return array();
    }

    public function getTaxCodes($fromDate, $toDate)
    {
        return array(
            new DkCanonicalTaxCode(array(
                'taxCode' => 'Salg25',
                'taxType' => 'VAT',
                'description' => 'Salgsmoms (udgående moms)',
                'standardTaxCode' => 'S1',
                'standardTaxCodeDescription' => 'Momspligtige salg (DK), 25% moms',
                'effectiveDate' => '2026-01-01',
                'expirationDate' => '2099-12-31',
                'taxPercentage' => '25',
                'countryCode' => 'DK',
            )),
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
                        'debit' => '312.5',
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
                        'taxInformation' => array(
                            array(
                                'taxCode' => 'Salg25',
                                'taxPercentage' => '25',
                                'taxBase' => '250',
                                'taxAmount' => '62.5',
                                'countryCode' => 'DK',
                            ),
                        ),
                    ),
                    array(
                        'lineId' => '3',
                        'accountCode' => '2600',
                        'debit' => '0',
                        'credit' => '62.5',
                        'description' => 'Salgsmoms',
                        'sourceDocumentRef' => 'BILAG-1',
                    ),
                ),
            )),
        );
    }
}
