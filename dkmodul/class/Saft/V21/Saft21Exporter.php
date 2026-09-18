<?php

require_once __DIR__.'/../../Accounting/Canonical/AccountingDataProviderInterface.php';
require_once __DIR__.'/../../Accounting/Canonical/Decimal.php';
require_once __DIR__.'/../SchemaRegistry.php';

class DkSaft21Exporter
{
    private $provider;
    private $accountMappingService;

    public function __construct(DkAccountingDataProviderInterface $provider, $accountMappingService = null)
    {
        $this->provider = $provider;
        $this->accountMappingService = $accountMappingService;
    }

    public function export($fromDate, $toDate, array $options = array())
    {
        $this->assertDate($fromDate);
        $this->assertDate($toDate);

        if ($toDate < $fromDate) {
            throw new InvalidArgumentException('SAF-T end date cannot be before start date');
        }

        $company = $this->provider->getCompanyContext();
        $accounts = $this->provider->getAccounts($fromDate, $toDate);
        $taxCodes = $this->provider->getTaxCodes($fromDate, $toDate);
        $transactions = $this->provider->getTransactions($fromDate, $toDate);

        $defaultCurrency = $this->requiredValue(
            isset($options['defaultCurrency']) ? $options['defaultCurrency'] : ($company['currencyCode'] ?? null),
            'Default currency'
        );

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $root = $doc->createElementNS(DkSaftSchemaRegistry::NAMESPACE_URI, 'AuditFile');
        $doc->appendChild($root);

        $this->appendHeader($doc, $root, $company, $fromDate, $toDate, $defaultCurrency, $options);
        $this->appendMasterFiles($doc, $root, $accounts, $taxCodes, $fromDate, $options);
        $this->appendGeneralLedgerEntries($doc, $root, $transactions, $defaultCurrency);

        return $doc->saveXML();
    }

    private function appendHeader(DOMDocument $doc, DOMElement $root, array $company, $fromDate, $toDate, $defaultCurrency, array $options)
    {
        $header = $this->element($doc, $root, 'Header');

        $this->element($doc, $header, 'AuditFileVersion', DkSaftSchemaRegistry::VERSION);
        $this->element($doc, $header, 'AuditFileCountry', 'DK');

        if (!empty($company['regionCode'])) {
            $this->element($doc, $header, 'AuditFileRegion', $company['regionCode']);
        }

        $createdDate = isset($options['createdDate']) ? (string) $options['createdDate'] : gmdate('Y-m-d');
        $this->assertDate($createdDate);

        $this->element($doc, $header, 'AuditFileDateCreated', $createdDate);
        $this->element($doc, $header, 'SoftwareCompanyName', $options['softwareCompanyName'] ?? 'Dolibarr DK');
        $this->element($doc, $header, 'SoftwareID', $options['softwareId'] ?? 'Dolibarr DK');
        $this->element($doc, $header, 'SoftwareVersion', $options['softwareVersion'] ?? 'development');

        $companyNode = $this->element($doc, $header, 'Company');
        $registration = $this->requiredValue($company['registrationNumber'] ?? null, 'Company registration number');

        if (preg_match('/^\d{8}$/', $registration)) {
            $this->element($doc, $companyNode, 'CVR', $registration);
        } else {
            $this->element($doc, $companyNode, 'RegistrationNumber', $registration);
        }

        $this->element($doc, $companyNode, 'Name', $this->requiredValue($company['name'] ?? null, 'Company name'));

        $address = $company['address'] ?? array();
        if (!is_array($address)) {
            $address = array(
                'streetName' => $company['address'] ?? null,
                'postalCode' => $company['postalCode'] ?? null,
                'city' => $company['city'] ?? null,
                'countryCode' => $company['countryCode'] ?? null,
            );
        }
        if (empty($address)) {
            $address = array(
                'streetName' => $company['streetName'] ?? null,
                'postalCode' => $company['postalCode'] ?? null,
                'city' => $company['city'] ?? null,
                'countryCode' => $company['countryCode'] ?? null,
            );
        }

        $addressNode = $this->element($doc, $companyNode, 'Address');
        $this->element($doc, $addressNode, 'StreetName', $this->requiredValue($address['streetName'] ?? null, 'Company street/address'));
        if (!empty($address['number'])) {
            $this->element($doc, $addressNode, 'Number', $address['number']);
        }
        if (!empty($address['additionalDetail'])) {
            $this->element($doc, $addressNode, 'AdditionalAddressDetail', $address['additionalDetail']);
        }
        if (!empty($address['building'])) {
            $this->element($doc, $addressNode, 'Building', $address['building']);
        }
        $this->element($doc, $addressNode, 'City', $this->requiredValue($address['city'] ?? null, 'Company city'));
        $this->element($doc, $addressNode, 'PostalCode', $this->requiredValue($address['postalCode'] ?? null, 'Company postal code'));
        if (!empty($address['region'])) {
            $this->element($doc, $addressNode, 'Region', $address['region']);
        }
        $this->element($doc, $addressNode, 'Country', $this->requiredValue($address['countryCode'] ?? null, 'Company country'));

        if (!empty($company['phone']) || !empty($company['email'])) {
            $contact = $this->element($doc, $companyNode, 'Contact');
            if (!empty($company['phone'])) {
                $this->element($doc, $contact, 'Telephone', $company['phone']);
            }
            if (!empty($company['email'])) {
                $this->element($doc, $contact, 'Email', $company['email']);
            }
        }

        if (!empty($company['taxRegistrationNumber'])) {
            $tax = $this->element($doc, $companyNode, 'TaxRegistration');
            $this->element($doc, $tax, 'TaxRegistrationNumber', $company['taxRegistrationNumber']);
            $this->element($doc, $tax, 'TaxType', 'VAT');
            $this->element($doc, $tax, 'Country', $company['countryCode'] ?? 'DK');
        }

        $bankAccounts = $company['bankAccounts'] ?? array();
        if (!is_array($bankAccounts) || count($bankAccounts) === 0) {
            throw new InvalidArgumentException('At least one company bank account is required by SAF-T 2.1');
        }

        foreach ($bankAccounts as $bankAccount) {
            $bank = $this->element($doc, $companyNode, 'BankAccount');

            if (!empty($bankAccount['iban'])) {
                $this->element($doc, $bank, 'IBANNumber', $bankAccount['iban']);
            } else {
                $this->element($doc, $bank, 'BankAccountNumber', $this->requiredValue($bankAccount['number'] ?? null, 'Bank account number'));
                $this->element($doc, $bank, 'SortCode', $this->requiredValue($bankAccount['sortCode'] ?? null, 'Bank sort code'));
            }

            if (!empty($bankAccount['bic'])) {
                $this->element($doc, $bank, 'BIC', $bankAccount['bic']);
            }
            if (!empty($bankAccount['currencyCode'])) {
                $this->element($doc, $bank, 'CurrencyCode', $bankAccount['currencyCode']);
            }
            if (!empty($bankAccount['accountId'])) {
                $this->element($doc, $bank, 'AccountID', $bankAccount['accountId']);
            }
        }

        $this->element($doc, $header, 'DefaultCurrencyCode', $defaultCurrency);

        $selection = $this->element($doc, $header, 'SelectionCriteria');
        $this->element($doc, $selection, 'SelectionStartDate', $fromDate);
        $this->element($doc, $selection, 'SelectionEndDate', $toDate);
        $this->element($doc, $selection, 'PeriodStartYear', substr($fromDate, 0, 4));
        $this->element($doc, $selection, 'PeriodEndYear', substr($toDate, 0, 4));

        if (!empty($options['headerComment'])) {
            $this->element($doc, $header, 'HeaderComment', $options['headerComment']);
        }

        if (!empty($options['taxAccountingBasis'])) {
            $this->element($doc, $header, 'TaxAccountingBasis', $options['taxAccountingBasis']);
        }

        $this->element(
            $doc,
            $header,
            'TaxEntity',
            $options['taxEntity'] ?? $registration
        );

        if (!empty($options['userId'])) {
            $this->element($doc, $header, 'UserID', $options['userId']);
        }
    }

    private function appendMasterFiles(DOMDocument $doc, DOMElement $root, array $accounts, array $taxCodes, $mappingDate, array $options)
    {
        if (count($accounts) === 0) {
            throw new InvalidArgumentException('SAF-T 2.1 requires at least one General Ledger account');
        }

        $masterFiles = $this->element($doc, $root, 'MasterFiles');
        $ledgerAccounts = $this->element($doc, $masterFiles, 'GeneralLedgerAccounts');
        $this->element($doc, $ledgerAccounts, 'NameOfStandardAccount', DkSaftSchemaRegistry::STANDARD_ACCOUNT_NAME);
        $this->element($doc, $ledgerAccounts, 'VersionOfStandardAccount', DkSaftSchemaRegistry::STANDARD_ACCOUNT_VERSION);

        foreach ($accounts as $account) {
            $accountCode = $this->requiredValue($account['accountCode'] ?? null, 'Account code');
            $node = $this->element($doc, $ledgerAccounts, 'Account');

            $this->element($doc, $node, 'AccountID', $accountCode);
            $this->element($doc, $node, 'AccountDescription', $this->requiredValue($account['label'] ?? null, 'Account description'));

            $standardAccountId = $account['standardAccountId'] ?? null;
            if ($standardAccountId === null && $this->accountMappingService !== null) {
                $company = $this->provider->getCompanyContext();
                $mapping = $this->accountMappingService->resolve((int) ($company['id'] ?? 0), $accountCode, $mappingDate);
                if ($mapping && ($mapping['standardVersion'] ?? null) === DkSaftSchemaRegistry::STANDARD_ACCOUNT_VERSION) {
                    $standardAccountId = $mapping['standardAccount'];
                }
            }

            $strictMapping = !array_key_exists('strictStandardMapping', $options) || (bool) $options['strictStandardMapping'];
            if ($strictMapping && empty($standardAccountId)) {
                throw new InvalidArgumentException('Missing standard account mapping for account '.$accountCode);
            }
            if (!empty($standardAccountId)) {
                $this->element($doc, $node, 'StandardAccountID', $standardAccountId);
            }

            $this->element($doc, $node, 'AccountType', $this->mapAccountType($account['accountType'] ?? 'Other'));
            if (!empty($account['creationDate'])) {
                $this->element($doc, $node, 'AccountCreationDate', substr((string) $account['creationDate'], 0, 10));
            }

            $this->appendBalance($doc, $node, 'Opening', $account['openingBalance'] ?? '0');
            $this->appendBalance($doc, $node, 'Closing', $account['closingBalance'] ?? '0');
        }

        if (count($taxCodes) === 0) {
            throw new InvalidArgumentException('SAF-T 2.1 requires a VAT TaxTable');
        }

        $taxTable = $this->element($doc, $masterFiles, 'TaxTable');
        $taxEntry = $this->element($doc, $taxTable, 'TaxTableEntry');
        $this->element($doc, $taxEntry, 'TaxType', 'VAT');
        $this->element($doc, $taxEntry, 'Description', 'VAT');

        foreach ($taxCodes as $taxCode) {
            $localCode = $this->requiredValue($taxCode['taxCode'] ?? null, 'Tax code');
            $details = $this->element($doc, $taxEntry, 'TaxCodeDetails');

            $this->element($doc, $details, 'TaxCode', $localCode);

            $strictTaxMapping = !array_key_exists('strictStandardTaxMapping', $options)
                || (bool) $options['strictStandardTaxMapping'];
            $standardTaxCode = trim((string) ($taxCode['standardTaxCode'] ?? ''));

            if ($strictTaxMapping && $standardTaxCode === '') {
                throw new InvalidArgumentException('Missing standard VAT mapping for tax code '.$localCode);
            }

            if ($standardTaxCode !== '') {
                $this->element($doc, $details, 'StandardTaxCode', $standardTaxCode);
            }
            if (!empty($taxCode['effectiveDate'])) {
                $this->element($doc, $details, 'EffectiveDate', $taxCode['effectiveDate']);
            }
            if (!empty($taxCode['expirationDate'])) {
                $this->element($doc, $details, 'ExpirationDate', $taxCode['expirationDate']);
            }

            $this->element(
                $doc,
                $details,
                'Description',
                $this->requiredValue($taxCode['description'] ?? null, 'Tax code description')
            );

            if (isset($taxCode['taxPercentage']) && $taxCode['taxPercentage'] !== '') {
                $this->element($doc, $details, 'TaxPercentage', $taxCode['taxPercentage']);
            }
            if (!empty($taxCode['countryCode'])) {
                $this->element($doc, $details, 'Country', $taxCode['countryCode']);
            }
        }
    }

    private function appendGeneralLedgerEntries(DOMDocument $doc, DOMElement $root, array $transactions, $defaultCurrency)
    {
        $gle = $this->element($doc, $root, 'GeneralLedgerEntries');

        $numberOfEntries = 0;
        $totalDebit = DkCanonicalDecimal::normalize('0');
        $totalCredit = DkCanonicalDecimal::normalize('0');
        $journals = array();

        foreach ($transactions as $transaction) {
            if (!isset($journals[$transaction->journalCode])) {
                $journals[$transaction->journalCode] = array();
            }
            $journals[$transaction->journalCode][] = $transaction;

            foreach ($transaction->lines as $line) {
                $numberOfEntries++;
                $totalDebit = DkCanonicalDecimal::add($totalDebit, $line->debit);
                $totalCredit = DkCanonicalDecimal::add($totalCredit, $line->credit);
            }
        }

        $this->element($doc, $gle, 'NumberOfEntries', (string) $numberOfEntries);
        $this->element($doc, $gle, 'TotalDebit', $totalDebit);
        $this->element($doc, $gle, 'TotalCredit', $totalCredit);

        if (count($journals) === 0) {
            throw new InvalidArgumentException('SAF-T 2.1 requires at least one journal');
        }

        foreach ($journals as $journalCode => $journalTransactions) {
            $journal = $this->element($doc, $gle, 'Journal');
            $first = $journalTransactions[0];

            $this->element($doc, $journal, 'JournalID', $journalCode);
            $this->element(
                $doc,
                $journal,
                'Description',
                !empty($first->journalDescription) ? $first->journalDescription : $journalCode
            );
            $this->element($doc, $journal, 'Type', 'GL');

            foreach ($journalTransactions as $transaction) {
                $tx = $this->element($doc, $journal, 'Transaction');
                $this->element($doc, $tx, 'TransactionID', $transaction->transactionId);
                $this->element($doc, $tx, 'Period', (string) ((int) substr($transaction->transactionDate, 5, 2)));
                $this->element($doc, $tx, 'PeriodYear', substr($transaction->transactionDate, 0, 4));
                $this->element($doc, $tx, 'TransactionDate', $transaction->transactionDate);

                if ($transaction->actor !== '') {
                    $this->element($doc, $tx, 'SourceID', $transaction->actor);
                }

                $description = $transaction->description ?: ($transaction->documentRef ?: $transaction->transactionId);
                $this->element($doc, $tx, 'Description', $description);
                $this->element($doc, $tx, 'SystemEntryDate', substr($transaction->registrationDateTime, 0, 10));
                $this->element($doc, $tx, 'GLPostingDate', $transaction->transactionDate);
                $this->element(
                    $doc,
                    $tx,
                    'SystemID',
                    $transaction->sourcePieceNumber !== null
                        ? (string) $transaction->sourcePieceNumber
                        : $transaction->transactionId
                );

                foreach ($transaction->lines as $line) {
                    $lineNode = $this->element($doc, $tx, 'Line');
                    $this->element($doc, $lineNode, 'RecordID', $line->lineId);
                    $this->element($doc, $lineNode, 'AccountID', $line->accountCode);

                    if (!empty($line->sourceDocumentRef)) {
                        $this->element($doc, $lineNode, 'SourceDocumentID', $line->sourceDocumentRef);
                    }

                    $this->element(
                        $doc,
                        $lineNode,
                        'Description',
                        $line->description ?: $description
                    );

                    if (!DkCanonicalDecimal::equals($line->debit, '0')) {
                        $this->appendAmount($doc, $lineNode, 'DebitAmount', $line->debit, $line, $defaultCurrency);
                    } else {
                        $this->appendAmount($doc, $lineNode, 'CreditAmount', $line->credit, $line, $defaultCurrency);
                    }

                    if (!empty($line->taxCode)) {
                        $tax = $this->element($doc, $lineNode, 'TaxInformation');
                        $this->element($doc, $tax, 'TaxType', 'VAT');
                        $this->element($doc, $tax, 'TaxCode', $line->taxCode);
                    }
                }
            }
        }
    }

    private function appendAmount(DOMDocument $doc, DOMElement $parent, $name, $amount, $line, $defaultCurrency)
    {
        $currencyCode = $line->currencyCode ?: $defaultCurrency;
        $currencyAmount = $line->currencyAmount;

        if ($currencyCode !== $defaultCurrency) {
            if ($currencyAmount === null || $currencyAmount === '') {
                throw new InvalidArgumentException('Foreign-currency bookkeeping line '.$line->lineId.' has no currency amount');
            }
            throw new InvalidArgumentException('Foreign-currency SAF-T exchange-rate export is not implemented yet for line '.$line->lineId);
        }

        $node = $this->element($doc, $parent, $name);
        $this->element($doc, $node, 'Amount', $amount);
        $this->element($doc, $node, 'CurrencyCode', $defaultCurrency);
        $this->element($doc, $node, 'CurrencyAmount', $amount);
        $this->element($doc, $node, 'ExchangeRate', '1.0');
    }

    private function appendBalance(DOMDocument $doc, DOMElement $parent, $prefix, $value)
    {
        $normalized = DkCanonicalDecimal::normalize($value);
        $negative = isset($normalized[0]) && $normalized[0] === '-';
        $amount = $negative ? substr($normalized, 1) : $normalized;

        $this->element(
            $doc,
            $parent,
            $prefix.($negative ? 'CreditBalance' : 'DebitBalance'),
            $amount
        );
    }

    private function mapAccountType($type)
    {
        $type = strtoupper(trim((string) $type));

        $map = array(
            'ASSET' => 'Asset',
            'LIABILITY' => 'Liability',
            'INCOME' => 'Sale',
            'SALE' => 'Sale',
            'EXPENSE' => 'Expense',
            'OTHER' => 'Other',
        );

        return $map[$type] ?? 'Other';
    }

    private function element(DOMDocument $doc, DOMElement $parent, $name, $value = null)
    {
        $element = $doc->createElementNS(DkSaftSchemaRegistry::NAMESPACE_URI, $name);

        if ($value !== null) {
            $element->appendChild($doc->createTextNode((string) $value));
        }

        $parent->appendChild($element);
        return $element;
    }

    private function requiredValue($value, $label)
    {
        $value = trim((string) $value);
        if ($value === '') {
            throw new InvalidArgumentException($label.' is required for SAF-T 2.1');
        }
        return $value;
    }

    private function assertDate($value)
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value)) {
            throw new InvalidArgumentException('SAF-T dates must use YYYY-MM-DD');
        }
    }
}
