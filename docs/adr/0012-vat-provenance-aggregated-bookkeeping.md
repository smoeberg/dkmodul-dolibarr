# ADR-0012 — VAT provenance for aggregated bookkeeping lines

Status: Accepted

## Context

Dolibarr Advanced Accounting aggregates ordinary customer and supplier invoice
lines when transferring invoices to `accounting_bookkeeping`.

For these flows Dolibarr deliberately writes:

- `doc_type = customer_invoice` or `supplier_invoice`,
- `fk_doc = source invoice id`,
- `fk_docdet = 0`.

The source invoice lines still retain:

- `vat_src_code`,
- VAT percentage,
- tax base,
- VAT amount,
- `fk_code_ventilation` pointing to the accounting account.

Therefore a one-to-one bookkeeping-line to invoice-line relationship does not
exist and must not be invented.

## Decision

Dolibarr DK reconstructs VAT provenance read-only from the source document at
SAF-T/canonical mapping time.

For customer invoices:

```text
accounting_bookkeeping
  doc_type=customer_invoice
  fk_doc=<invoice>
  numero_compte=<account>
        |
        v
facturedet
  fk_facture=<invoice>
  fk_code_ventilation
  vat_src_code
  tva_tx
  total_ht
  total_tva
        |
        v
accounting_account.account_number=<account>
```

Supplier invoices use the equivalent `facture_fourn_det` fields, with
`tva` as the source VAT amount.

Source lines are grouped by:

- source document,
- accounting account,
- local VAT code,
- VAT percentage.

The result becomes zero or more canonical `taxComponents` on one bookkeeping
line.

## SAF-T consequence

The official Danish SAF-T 2.1 XSD allows:

```xml
<TaxInformation minOccurs="0" maxOccurs="unbounded">
```

on a General Ledger line.

An aggregated Dolibarr line can therefore retain multiple VAT components
without splitting or rewriting the immutable bookkeeping entry.

Each component is mapped, effective-dated, from the Dolibarr local VAT code to
the official Danish standard VAT code.

## Strict failure rules

Export fails if:

- a taxable source line has no `vat_src_code`,
- no valid standard VAT mapping exists on the transaction date,
- the mapping targets the wrong standard version,
- an official fixed VAT percentage conflicts with the source percentage.

A true no-VAT source line with no local VAT code produces no
`TaxInformation` block.

## Consequences

- no Dolibarr core fork is required for ordinary customer/supplier invoice VAT
  provenance;
- the original immutable bookkeeping lines remain unchanged;
- provenance depends on source invoice lines remaining available for the
  required retention period;
- other document types (expense reports, assets, special discounts, etc.) need
  explicit provenance adapters before they can claim equivalent SAF-T coverage.
