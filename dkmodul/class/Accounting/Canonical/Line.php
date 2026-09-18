<?php

require_once __DIR__.'/Decimal.php';

class DkCanonicalLine
{
    public $lineId;
    public $accountCode;
    public $debit;
    public $credit;
    public $partyId;
    public $taxCode;
    public $taxComponents = array();
    public $currencyCode;
    public $currencyAmount;
    public $description;
    public $sourceDocumentRef;

    public function __construct(array $data)
    {
        $this->lineId = (string) ($data['lineId'] ?? '');
        $this->accountCode = (string) ($data['accountCode'] ?? '');
        $this->debit = DkCanonicalDecimal::normalize($data['debit'] ?? '0');
        $this->credit = DkCanonicalDecimal::normalize($data['credit'] ?? '0');
        $this->partyId = isset($data['partyId']) ? (string) $data['partyId'] : null;
        $this->taxCode = isset($data['taxCode']) ? (string) $data['taxCode'] : null;
        $this->taxComponents = array();

        foreach (($data['taxComponents'] ?? array()) as $component) {
            if (!is_array($component)) {
                throw new InvalidArgumentException('Canonical tax component must be an array');
            }

            $taxCode = trim((string) ($component['taxCode'] ?? ''));
            if ($taxCode === '') {
                throw new InvalidArgumentException('Canonical tax component requires a tax code');
            }

            $this->taxComponents[] = array(
                'taxCode' => $taxCode,
                'taxPercentage' => DkCanonicalDecimal::normalize($component['taxPercentage'] ?? '0'),
                'taxBase' => DkCanonicalDecimal::normalize($component['taxBase'] ?? '0'),
                'taxAmount' => DkCanonicalDecimal::normalize($component['taxAmount'] ?? '0'),
                'taxBaseDescription' => isset($component['taxBaseDescription'])
                    ? (string) $component['taxBaseDescription']
                    : null,
            );
        }
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
