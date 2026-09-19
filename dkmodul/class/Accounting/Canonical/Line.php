<?php

require_once __DIR__.'/Decimal.php';
require_once __DIR__.'/TaxInformation.php';

final readonly class DkCanonicalLine
{
    public string $lineId;
    public string $accountCode;
    public string $debit;
    public string $credit;
    public ?string $partyId;
    public ?string $taxCode;
    public array $taxInformation;
    public ?string $currencyCode;
    public ?string $currencyAmount;
    public ?string $description;
    public ?string $sourceDocumentRef;

    public function __construct(array $data)
    {
        $this->lineId = (string) ($data['lineId'] ?? '');
        $this->accountCode = (string) ($data['accountCode'] ?? '');
        $this->debit = DkCanonicalDecimal::normalize($data['debit'] ?? '0');
        $this->credit = DkCanonicalDecimal::normalize($data['credit'] ?? '0');
        $this->partyId = isset($data['partyId']) ? (string) $data['partyId'] : null;
        $this->taxCode = isset($data['taxCode']) ? (string) $data['taxCode'] : null;

        $taxInformation = array();
        foreach (($data['taxInformation'] ?? array()) as $tax) {
            $taxInformation[] = $tax instanceof DkCanonicalTaxInformation
                ? $tax
                : new DkCanonicalTaxInformation((array) $tax);
        }

        // Backward-compatible single-code input for callers not yet migrated.
        if ($this->taxCode !== null && $this->taxCode !== '' && count($taxInformation) === 0) {
            $taxInformation[] = new DkCanonicalTaxInformation(array(
                'taxCode' => $this->taxCode,
            ));
        }
        $this->taxInformation = $taxInformation;

        $this->currencyCode = isset($data['currencyCode']) ? (string) $data['currencyCode'] : null;
        $this->currencyAmount = isset($data['currencyAmount'])
            ? DkCanonicalDecimal::normalize($data['currencyAmount'])
            : null;
        $this->description = isset($data['description']) ? (string) $data['description'] : null;
        $this->sourceDocumentRef = isset($data['sourceDocumentRef']) ? (string) $data['sourceDocumentRef'] : null;

        if ($this->lineId === '') {
            throw new InvalidArgumentException('Canonical line id is required');
        }
        if ($this->accountCode === '') {
            throw new InvalidArgumentException('Canonical account code is required');
        }
        $hasDebit = !DkCanonicalDecimal::equals($this->debit, '0');
        $hasCredit = !DkCanonicalDecimal::equals($this->credit, '0');

        if ($hasDebit && $hasCredit) {
            throw new InvalidArgumentException('A canonical line cannot have both debit and credit amounts');
        }
        if (!$hasDebit && !$hasCredit) {
            throw new InvalidArgumentException('A canonical line must have a debit or credit amount');
        }
    }
}
