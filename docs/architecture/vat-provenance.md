# VAT provenance from Dolibarr to SAF-T 2.1

## Problem

Dolibarr Advanced Accounting does not preserve a one-to-one relation between every
invoice detail and every row in `accounting_bookkeeping`.

For customer and supplier invoices, multiple invoice details are deliberately
aggregated into bookkeeping rows by accounting account, and `fk_docdet` is
normally stored as `0`.

Therefore this is not reliable:

```text
accounting_bookkeeping.fk_docdet -> invoice line -> VAT code
```

## Decision

Dolibarr DK reconstructs source VAT provenance using:

```text
bookkeeping row
    |
    +-- doc_type
    +-- fk_doc
    +-- numero_compte
            |
            v
source invoice details
    |
    +-- fk_code_ventilation -> accounting_account.account_number
    +-- vat_src_code
    +-- tva_tx
    +-- total_ht
    +-- total_tva / tva
            |
            v
Canonical TaxInformation[]
```

Only source invoice details ventilated to the same accounting account as the
bookkeeping line are included.

This means:

- the revenue/expense line receives source VAT provenance,
- the receivable/payable line does not,
- the VAT control-account line does not receive duplicate source VAT provenance.

## Multiple VAT codes on one bookkeeping line

The official SAF-T 2.1 XSD defines GeneralLedgerEntries/Line/TaxInformation with
`maxOccurs="unbounded"`.

That matches Dolibarr's aggregation model. If invoice details using several VAT
codes are aggregated to the same bookkeeping account, the canonical line retains
one actual ledger line and carries multiple VAT provenance records.

Dolibarr DK does **not** split or invent General Ledger entries merely to attach
VAT metadata.

## Mapping boundary

Canonical tax information contains the local/source facts:

- local `vat_src_code`,
- VAT percentage,
- tax base,
- tax amount,
- country.

The SAF-T layer is responsible for mapping the local code to the public Danish
`StandardTaxCode` through the effective-dated `llx_dk_vat_mapping` table.

No mapping is inferred from percentage alone.

## Current source coverage

Initial implementation:

- customer invoices,
- supplier invoices.

Future source adapters must be added explicitly for other source document types,
for example expense reports where VAT information is material.

## Failure mode

When a canonical line contains a local VAT code but no valid public mapping exists
for the transaction date, strict SAF-T export fails.

This is intentional. A missing mapping must not silently produce semantically
incomplete compliance output.
