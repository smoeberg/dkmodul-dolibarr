<?php

require_once __DIR__.'/InvoiceParty.php';
require_once __DIR__.'/InvoiceLine.php';

final readonly class DkCanonicalInvoice
{
    public int $sourceInvoiceId;
    public string $invoiceId;
    public string $uuid;
    public string $issueDate;
    public string $dueDate;
    public string $currencyCode;
    public string $orderReference;
    public DkCanonicalInvoiceParty $supplier;
    public DkCanonicalInvoiceParty $customer;
    public array $lines;
    public string $taxExclusiveAmount;
    public string $taxAmount;
    public string $taxInclusiveAmount;
    public string $payableAmount;
    public string $paymentMeansCode;
    public string $paymentId;
    public string $bankAccount;
    public string $bankRegistrationNumber;

    public function __construct(array $data)
    {
        $this->sourceInvoiceId = (int) ($data['sourceInvoiceId'] ?? 0);
        foreach (array('invoiceId', 'uuid', 'issueDate', 'dueDate', 'currencyCode', 'orderReference', 'paymentMeansCode', 'paymentId', 'bankAccount', 'bankRegistrationNumber') as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            if ($value === '') {
                throw new InvalidArgumentException('Canonical invoice requires '.$field);
            }
            $this->{$field} = $value;
        }
        if ($this->sourceInvoiceId <= 0) {
            throw new InvalidArgumentException('Canonical invoice requires source invoice id');
        }
        $this->supplier = $data['supplier'] instanceof DkCanonicalInvoiceParty ? $data['supplier'] : new DkCanonicalInvoiceParty((array) $data['supplier']);
        $this->customer = $data['customer'] instanceof DkCanonicalInvoiceParty ? $data['customer'] : new DkCanonicalInvoiceParty((array) $data['customer']);
        $lines = array();
        foreach ((array) ($data['lines'] ?? array()) as $line) {
            $lines[] = $line instanceof DkCanonicalInvoiceLine ? $line : new DkCanonicalInvoiceLine((array) $line);
        }
        if (!$lines) {
            throw new InvalidArgumentException('Canonical invoice requires at least one line');
        }
        $this->lines = $lines;
        $this->taxExclusiveAmount = DkCanonicalDecimal::normalize($data['taxExclusiveAmount'] ?? '0');
        $this->taxAmount = DkCanonicalDecimal::normalize($data['taxAmount'] ?? '0');
        $this->taxInclusiveAmount = DkCanonicalDecimal::normalize($data['taxInclusiveAmount'] ?? '0');
        $this->payableAmount = DkCanonicalDecimal::normalize($data['payableAmount'] ?? $this->taxInclusiveAmount);

        $lineTotal = DkCanonicalDecimal::normalize('0');
        $lineTaxTotal = DkCanonicalDecimal::normalize('0');
        foreach ($this->lines as $line) {
            $lineTotal = DkCanonicalDecimal::add($lineTotal, $line->lineExtensionAmount);
            $lineTaxTotal = DkCanonicalDecimal::add($lineTaxTotal, $line->taxAmount);
        }
        if (!DkCanonicalDecimal::equals($lineTotal, $this->taxExclusiveAmount)
            || !DkCanonicalDecimal::equals($lineTaxTotal, $this->taxAmount)
            || !DkCanonicalDecimal::equals(DkCanonicalDecimal::add($this->taxExclusiveAmount, $this->taxAmount), $this->taxInclusiveAmount)) {
            throw new InvalidArgumentException('Canonical invoice totals are inconsistent');
        }
    }
}
