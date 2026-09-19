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
    public $standardTaxCode;
    public $taxPercentage;
    public $taxBase;
    public $taxAmount;
    public $countryCode;
    public $description;

    public function __construct(array $data)
    {
        $this->taxType = isset($data['taxType']) ? (string) $data['taxType'] : 'VAT';
        $this->taxCode = trim((string) ($data['taxCode'] ?? ''));
        $this->standardTaxCode = isset($data['standardTaxCode']) && trim((string) $data['standardTaxCode']) !== ''
            ? trim((string) $data['standardTaxCode'])
            : null;
        $this->taxPercentage = !array_key_exists('taxPercentage', $data) || $data['taxPercentage'] === null
            ? null
            : DkCanonicalDecimal::normalize($data['taxPercentage']);
        $this->taxBase = !array_key_exists('taxBase', $data) || $data['taxBase'] === null
            ? null
            : DkCanonicalDecimal::normalize($data['taxBase']);
        $this->taxAmount = !array_key_exists('taxAmount', $data) || $data['taxAmount'] === null
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
