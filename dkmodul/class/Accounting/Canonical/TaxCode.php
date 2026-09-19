<?php

require_once __DIR__.'/Record.php';
require_once __DIR__.'/Decimal.php';

final readonly class DkCanonicalTaxCode extends DkCanonicalRecord
{
    public function __construct(array $data)
    {
        $this->setValues(array(
            'taxCode' => self::required($data, 'taxCode'),
            'taxType' => trim((string) ($data['taxType'] ?? 'VAT')) ?: 'VAT',
            'description' => self::required($data, 'description'),
            'standardTaxCode' => isset($data['standardTaxCode']) ? trim((string) $data['standardTaxCode']) : null,
            'standardTaxCodeDescription' => isset($data['standardTaxCodeDescription']) ? trim((string) $data['standardTaxCodeDescription']) : null,
            'effectiveDate' => isset($data['effectiveDate']) ? (string) $data['effectiveDate'] : null,
            'expirationDate' => isset($data['expirationDate']) ? (string) $data['expirationDate'] : null,
            'taxPercentage' => isset($data['taxPercentage']) ? DkCanonicalDecimal::normalize($data['taxPercentage']) : null,
            'countryCode' => isset($data['countryCode']) ? trim((string) $data['countryCode']) : null,
            'sourceVatType' => isset($data['sourceVatType']) ? (int) $data['sourceVatType'] : null,
        ));
    }
}
