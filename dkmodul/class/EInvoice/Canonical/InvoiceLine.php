<?php

require_once dirname(__DIR__, 2).'/Accounting/Canonical/Decimal.php';

final readonly class DkCanonicalInvoiceLine
{
    public string $id;
    public string $description;
    public string $quantity;
    public string $unitCode;
    public string $unitPrice;
    public string $lineExtensionAmount;
    public string $vatPercentage;
    public string $taxAmount;

    public function __construct(array $data)
    {
        $this->id = trim((string) ($data['id'] ?? ''));
        $this->description = trim((string) ($data['description'] ?? ''));
        $this->unitCode = trim((string) ($data['unitCode'] ?? 'EA')) ?: 'EA';
        if ($this->id === '' || $this->description === '') {
            throw new InvalidArgumentException('Canonical invoice line requires id and description');
        }
        $this->quantity = DkCanonicalDecimal::normalize($data['quantity'] ?? '1');
        $this->unitPrice = DkCanonicalDecimal::normalize($data['unitPrice'] ?? '0');
        $this->lineExtensionAmount = DkCanonicalDecimal::normalize($data['lineExtensionAmount'] ?? '0');
        $this->vatPercentage = DkCanonicalDecimal::normalize($data['vatPercentage'] ?? '0');
        $this->taxAmount = DkCanonicalDecimal::normalize($data['taxAmount'] ?? '0');
    }
}
