# VAT provenance edge cases

This document extends the source-document VAT provenance model with three cases
that must remain explicit in the canonical accounting model and SAF-T 2.1.

## Supplier invoices

Supplier invoice details are resolved from `facture_fourn_det` by:

- `fk_facture_fourn`,
- `fk_code_ventilation -> accounting_account.account_number`,
- `vat_src_code`,
- `tva_tx`,
- `total_ht`,
- `tva`.

VAT provenance belongs on the ventilated expense/purchase ledger line. It is not
duplicated onto the supplier-payable or VAT-control line.

## Zero-rate VAT

A source line with an explicit local VAT code and a 0% rate remains tax
information.

A zero rate is not the same as "no VAT provenance":

```text
local VAT code present + 0% rate
        -> canonical TaxInformation
        -> effective-dated StandardTaxCode
        -> SAF-T TaxInformation with TaxPercentage=0 and TaxAmount=0
```

A source line with no VAT code at all is a different case and must not be silently
assigned an arbitrary public VAT code.

## Credit notes

Dolibarr credit notes are negative invoices. Source invoice detail totals are
negative, and the bookkeeping direction is reversed.

Dolibarr DK therefore preserves the source signs:

- negative tax base remains negative,
- negative VAT amount remains negative,
- the VAT percentage itself remains positive,
- the same effective-dated VAT-code mapping rules apply.

The exporter does not convert the source tax amounts to absolute values.

## Evidence target

The integration test creates:

- one supplier invoice with 25% VAT,
- one customer invoice with an explicit 0% VAT code,
- one customer credit note with negative base and VAT,

and then verifies both the canonical representation and the resulting SAF-T 2.1
against the pinned official ERST XSD.
