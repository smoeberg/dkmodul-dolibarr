<?php

require_once __DIR__.'/../../Accounting/Canonical/Decimal.php';
require_once __DIR__.'/../../Accounting/Canonical/Transaction.php';
require_once __DIR__.'/../../Accounting/Canonical/TaxInformation.php';
require_once __DIR__.'/../SaftValidator.php';
require_once __DIR__.'/../SchemaRegistry.php';
require_once __DIR__.'/Saft21ImportPackage.php';

class DkSaft21Importer
{
    private $validator;

    public function __construct($validator = null)
    {
        $this->validator = $validator ?: new DkSaftValidator();
    }

    public function importXml($xml, $xsdPath)
    {
        $xml = (string) $xml;
        $this->validator->validateXml($xml, $xsdPath);

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        if (!$dom->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('Unable to parse validated SAF-T XML');
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('saf', DkSaftSchemaRegistry::NAMESPACE_URI);

        $version = $this->value($xpath, '/saf:AuditFile/saf:Header/saf:AuditFileVersion');
        if ($version !== DkSaftSchemaRegistry::VERSION) {
            throw new InvalidArgumentException('Unsupported SAF-T version '.$version);
        }

        $country = $this->value($xpath, '/saf:AuditFile/saf:Header/saf:AuditFileCountry');
        if ($country !== 'DK') {
            throw new InvalidArgumentException('Dolibarr DK SAF-T import requires AuditFileCountry DK');
        }

        $package = new DkSaft21ImportPackage(array(
            'sourceHash' => hash('sha256', $xml),
            'header' => $this->parseHeader($xpath),
            'accounts' => $this->parseAccounts($xpath),
            'standardAccountName' => $this->optionalValue($xpath, '/saf:AuditFile/saf:MasterFiles/saf:GeneralLedgerAccounts/saf:NameOfStandardAccount'),
            'standardAccountVersion' => $this->optionalValue($xpath, '/saf:AuditFile/saf:MasterFiles/saf:GeneralLedgerAccounts/saf:VersionOfStandardAccount'),
            'taxCodes' => $this->parseTaxCodes($xpath),
            'transactions' => $this->parseTransactions($xpath),
            'declaredNumberOfEntries' => $this->integerValue($xpath, '/saf:AuditFile/saf:GeneralLedgerEntries/saf:NumberOfEntries'),
            'declaredTotalDebit' => $this->value($xpath, '/saf:AuditFile/saf:GeneralLedgerEntries/saf:TotalDebit'),
            'declaredTotalCredit' => $this->value($xpath, '/saf:AuditFile/saf:GeneralLedgerEntries/saf:TotalCredit'),
        ));

        $this->assertPackageConsistency($package);

        return $package;
    }

    private function parseHeader(DOMXPath $xpath)
    {
        $registration = $this->optionalValue($xpath, '/saf:AuditFile/saf:Header/saf:Company/saf:CVR');
        if ($registration === null) {
            $registration = $this->optionalValue($xpath, '/saf:AuditFile/saf:Header/saf:Company/saf:RegistrationNumber');
        }

        return array(
            'auditFileVersion' => $this->value($xpath, '/saf:AuditFile/saf:Header/saf:AuditFileVersion'),
            'auditFileCountry' => $this->value($xpath, '/saf:AuditFile/saf:Header/saf:AuditFileCountry'),
            'createdDate' => $this->value($xpath, '/saf:AuditFile/saf:Header/saf:AuditFileDateCreated'),
            'softwareCompanyName' => $this->value($xpath, '/saf:AuditFile/saf:Header/saf:SoftwareCompanyName'),
            'softwareId' => $this->value($xpath, '/saf:AuditFile/saf:Header/saf:SoftwareID'),
            'softwareVersion' => $this->value($xpath, '/saf:AuditFile/saf:Header/saf:SoftwareVersion'),
            'companyRegistrationNumber' => $registration,
            'companyName' => $this->value($xpath, '/saf:AuditFile/saf:Header/saf:Company/saf:Name'),
            'defaultCurrencyCode' => $this->value($xpath, '/saf:AuditFile/saf:Header/saf:DefaultCurrencyCode'),
            'selectionStartDate' => $this->optionalValue($xpath, '/saf:AuditFile/saf:Header/saf:SelectionCriteria/saf:SelectionStartDate'),
            'selectionEndDate' => $this->optionalValue($xpath, '/saf:AuditFile/saf:Header/saf:SelectionCriteria/saf:SelectionEndDate'),
            'taxEntity' => $this->optionalValue($xpath, '/saf:AuditFile/saf:Header/saf:TaxEntity'),
        );
    }

    private function parseAccounts(DOMXPath $xpath)
    {
        $accounts = array();
        $seen = array();

        foreach ($xpath->query('/saf:AuditFile/saf:MasterFiles/saf:GeneralLedgerAccounts/saf:Account') as $node) {
            $id = $this->childValue($node, 'AccountID');
            if (isset($seen[$id])) {
                throw new InvalidArgumentException('Duplicate SAF-T AccountID '.$id);
            }
            $seen[$id] = true;

            $accounts[] = array(
                'accountId' => $id,
                'description' => $this->childValue($node, 'AccountDescription'),
                'standardAccountId' => $this->optionalChildValue($node, 'StandardAccountID'),
                'accountType' => $this->childValue($node, 'AccountType'),
                'openingDebit' => $this->optionalChildValue($node, 'OpeningDebitBalance'),
                'openingCredit' => $this->optionalChildValue($node, 'OpeningCreditBalance'),
                'closingDebit' => $this->optionalChildValue($node, 'ClosingDebitBalance'),
                'closingCredit' => $this->optionalChildValue($node, 'ClosingCreditBalance'),
            );
        }

        if (!$accounts) {
            throw new InvalidArgumentException('SAF-T import contains no GeneralLedgerAccounts');
        }

        return $accounts;
    }

    private function parseTaxCodes(DOMXPath $xpath)
    {
        $result = array();

        foreach ($xpath->query('/saf:AuditFile/saf:MasterFiles/saf:TaxTable/saf:TaxTableEntry') as $entry) {
            $taxType = $this->childValue($entry, 'TaxType');

            foreach ($this->childElements($entry, 'TaxCodeDetails') as $details) {
                $code = $this->childValue($details, 'TaxCode');
                $key = $taxType.'|'.$code;

                if (isset($result[$key])) {
                    throw new InvalidArgumentException('Duplicate SAF-T tax code '.$key);
                }

                $result[$key] = array(
                    'taxType' => $taxType,
                    'taxCode' => $code,
                    'standardTaxCode' => $this->optionalChildValue($details, 'StandardTaxCode'),
                    'effectiveDate' => $this->childValue($details, 'EffectiveDate'),
                    'expirationDate' => $this->optionalChildValue($details, 'ExpirationDate'),
                    'description' => $this->childValue($details, 'Description'),
                    'taxPercentage' => $this->optionalChildValue($details, 'TaxPercentage'),
                    'countryCode' => $this->optionalChildValue($details, 'Country'),
                );
            }
        }

        return array_values($result);
    }

    private function parseTransactions(DOMXPath $xpath)
    {
        $transactions = array();
        $transactionIds = array();

        foreach ($xpath->query('/saf:AuditFile/saf:GeneralLedgerEntries/saf:Journal') as $journalNode) {
            $journalId = $this->childValue($journalNode, 'JournalID');
            $journalDescription = $this->childValue($journalNode, 'Description');

            foreach ($this->childElements($journalNode, 'Transaction') as $transactionNode) {
                $transactionId = $this->childValue($transactionNode, 'TransactionID');
                if (isset($transactionIds[$transactionId])) {
                    throw new InvalidArgumentException('Duplicate SAF-T TransactionID '.$transactionId);
                }
                $transactionIds[$transactionId] = true;

                $recordIds = array();
                $lines = array();

                foreach ($this->childElements($transactionNode, 'Line') as $lineNode) {
                    $recordId = $this->childValue($lineNode, 'RecordID');
                    if (isset($recordIds[$recordId])) {
                        throw new InvalidArgumentException('Duplicate SAF-T RecordID '.$recordId.' in transaction '.$transactionId);
                    }
                    $recordIds[$recordId] = true;

                    $debitNode = $this->firstChildElement($lineNode, 'DebitAmount');
                    $creditNode = $this->firstChildElement($lineNode, 'CreditAmount');
                    if (($debitNode === null) === ($creditNode === null)) {
                        throw new InvalidArgumentException('SAF-T line '.$recordId.' must contain exactly one debit/credit amount');
                    }

                    $amountNode = $debitNode ?: $creditNode;
                    $amount = $this->childValue($amountNode, 'Amount');
                    $currencyCode = $this->optionalChildValue($amountNode, 'CurrencyCode');
                    $currencyAmount = $this->optionalChildValue($amountNode, 'CurrencyAmount');

                    $taxInformation = array();
                    foreach ($this->childElements($lineNode, 'TaxInformation') as $taxNode) {
                        $taxAmountNode = $this->firstChildElement($taxNode, 'TaxAmount');
                        $taxInformation[] = array(
                            'taxType' => $this->childValue($taxNode, 'TaxType'),
                            'taxCode' => $this->childValue($taxNode, 'TaxCode'),
                            'standardTaxCode' => $this->optionalChildValue($taxNode, 'StandardTaxCode'),
                            'taxPercentage' => $this->optionalChildValue($taxNode, 'TaxPercentage'),
                            'taxBase' => $this->optionalChildValue($taxNode, 'TaxBase'),
                            'taxAmount' => $taxAmountNode ? $this->childValue($taxAmountNode, 'Amount') : null,
                        );
                    }

                    $lines[] = array(
                        'lineId' => $recordId,
                        'accountCode' => $this->childValue($lineNode, 'AccountID'),
                        'debit' => $debitNode ? $amount : '0',
                        'credit' => $creditNode ? $amount : '0',
                        'currencyCode' => $currencyCode,
                        'currencyAmount' => $currencyAmount,
                        'description' => $this->childValue($lineNode, 'Description'),
                        'sourceDocumentRef' => $this->optionalChildValue($lineNode, 'SourceDocumentID'),
                        'taxInformation' => $taxInformation,
                    );
                }

                $sourceId = $this->optionalChildValue($transactionNode, 'SourceID');
                $systemEntryDate = $this->childValue($transactionNode, 'SystemEntryDate');
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $systemEntryDate)) {
                    $systemEntryDate .= ' 00:00:00';
                }

                $transactions[] = new DkCanonicalTransaction(array(
                    'transactionId' => $transactionId,
                    'sourcePieceNumber' => null,
                    'journalCode' => $journalId,
                    'journalDescription' => $journalDescription,
                    'transactionDate' => $this->childValue($transactionNode, 'TransactionDate'),
                    'registrationDateTime' => $systemEntryDate,
                    'validatedAt' => null,
                    'actor' => $sourceId !== null && $sourceId !== '' ? $sourceId : 'saft-import',
                    'sourceType' => 'saft_import',
                    'documentRef' => null,
                    'description' => $this->childValue($transactionNode, 'Description'),
                    'lines' => $lines,
                ));
            }
        }

        if (!$transactions) {
            throw new InvalidArgumentException('SAF-T import contains no GeneralLedgerEntries transactions');
        }

        return $transactions;
    }

    private function assertPackageConsistency(DkSaft21ImportPackage $package)
    {
        if ($package->declaredNumberOfEntries !== $package->lineCount()) {
            throw new InvalidArgumentException(
                'SAF-T NumberOfEntries does not match parsed line count'
            );
        }

        if (!DkCanonicalDecimal::equals($package->declaredTotalDebit, $package->totalDebit())) {
            throw new InvalidArgumentException('SAF-T TotalDebit does not match parsed lines');
        }

        if (!DkCanonicalDecimal::equals($package->declaredTotalCredit, $package->totalCredit())) {
            throw new InvalidArgumentException('SAF-T TotalCredit does not match parsed lines');
        }

        return true;
    }

    private function value(DOMXPath $xpath, $expression)
    {
        $value = trim((string) $xpath->evaluate('string('.$expression.')'));
        if ($value === '') {
            throw new InvalidArgumentException('Missing mandatory SAF-T value: '.$expression);
        }
        return $value;
    }

    private function optionalValue(DOMXPath $xpath, $expression)
    {
        $value = trim((string) $xpath->evaluate('string('.$expression.')'));
        return $value === '' ? null : $value;
    }

    private function integerValue(DOMXPath $xpath, $expression)
    {
        $value = $this->value($xpath, $expression);
        if (!preg_match('/^\d+$/', $value)) {
            throw new InvalidArgumentException('Expected integer SAF-T value: '.$expression);
        }
        return (int) $value;
    }

    private function childValue(DOMElement $parent, $localName)
    {
        $value = $this->optionalChildValue($parent, $localName);
        if ($value === null) {
            throw new InvalidArgumentException('Missing mandatory SAF-T child '.$localName);
        }
        return $value;
    }

    private function optionalChildValue(DOMElement $parent, $localName)
    {
        $node = $this->firstChildElement($parent, $localName);
        return $node ? trim((string) $node->textContent) : null;
    }

    private function firstChildElement(DOMElement $parent, $localName)
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $localName) {
                return $child;
            }
        }
        return null;
    }

    private function childElements(DOMElement $parent, $localName)
    {
        $result = array();
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $localName) {
                $result[] = $child;
            }
        }
        return $result;
    }
}
