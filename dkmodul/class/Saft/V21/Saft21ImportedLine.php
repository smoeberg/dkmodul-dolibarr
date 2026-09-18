<?php

require_once __DIR__.'/../../Accounting/Canonical/Decimal.php';
require_once __DIR__.'/../../Accounting/Canonical/TaxInformation.php';

/**
 * Raw-but-normalized line from an XSD-valid SAF-T file.
 *
 * Unlike the canonical ledger model this class represents untrusted import
 * input. Business-integrity checks happen during import analysis, not parsing.
 */
class DkSaft21ImportedLine
{
    public $lineId;
    public $accountCode;
    public $debit;
    public $credit;
    public $currencyCode;
    public $currencyAmount;
    public $description;
    public $sourceDocumentRef;
    public $taxInformation = array();

    public function __construct(array $data)
    {
        $this->lineId = trim((string) ($data['lineId'] ?? ''));
        $this->accountCode = trim((string) ($data['accountCode'] ?? ''));
        $this->debit = DkCanonicalDecimal::normalize($data['debit'] ?? '0');
        $this->credit = DkCanonicalDecimal::normalize($data['credit'] ?? '0');
        $this->currencyCode = isset($data['currencyCode']) && $data['currencyCode'] !== ''
            ? (string) $data['currencyCode']
            : null;
        $this->currencyAmount = isset($data['currencyAmount']) && $data['currencyAmount'] !== ''
            ? DkCanonicalDecimal::normalize($data['currencyAmount'])
            : null;
        $this->description = isset($data['description']) ? (string) $data['description'] : null;
        $this->sourceDocumentRef = isset($data['sourceDocumentRef']) && $data['sourceDocumentRef'] !== ''
            ? (string) $data['sourceDocumentRef']
            : null;

        foreach (($data['taxInformation'] ?? array()) as $tax) {
            $this->taxInformation[] = $tax instanceof DkCanonicalTaxInformation
                ? $tax
                : new DkCanonicalTaxInformation((array) $tax);
        }

        if ($this->lineId === '' || $this->accountCode === '') {
            throw new InvalidArgumentException('Imported SAF-T line requires RecordID and AccountID');
        }
    }
}
