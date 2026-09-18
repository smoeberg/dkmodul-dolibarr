<?php

require_once __DIR__.'/Decimal.php';

/**
 * Version-neutral source tax facts attached to a canonical ledger line.
 *
 * This class deliberately stores the source/local VAT code. Mapping to the
 * public Danish StandardTaxCode belongs to the SAF-T mapping layer.
 */
class DkCanonicalTaxInformation
{
    public $taxType;
    public $taxCode;
    public $taxPercentage;
    public $taxBase;
    public $taxAmount;
    public $countryCode;
    public $description;

    public function __construct(array $data)
    {
        $this->taxType = isset($data['taxType']) ? (string) $data['taxType'] : 'VAT';
        $this->taxCode = trim((string) ($data['taxCode'] ?? ''));
        $this->taxPercentage = $data['taxPercentage'] === null || !isset($data['taxPercentage'])
            ? null
            : DkCanonicalDecimal::normalize($data['taxPercentage']);
        $this->taxBase = $data['taxBase'] === null || !isset($data['taxBase'])
            ? null
            : DkCanonicalDecimal::normalize($data['taxBase']);
        $this->taxAmount = $data['taxAmount'] === null || !isset($data['taxAmount'])
            ? null
            : DkCanonicalDecimal::normalize($data['taxAmount']);
        $this->countryCode = isset($data['countryCode']) ? trim((string) $data['countryCode']) : null;
        $this->description = isset($data['description']) ? trim((string) $data['description']) : null;

        if ($this->taxCode === '') {
            throw new InvalidArgumentException('Canonical tax information requires a local tax code');
        }

        if ($this->taxType !== 'VAT') {
            throw new InvalidArgumentException('Only VAT tax information is supported by the Danish canonical profile');
        }
    }
}
