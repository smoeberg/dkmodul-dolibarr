# ADR-0011 — Versioned Danish VAT mapping

Status: Accepted

## Context

Dolibarr maintains local VAT codes in `c_tva`. Danish SAF-T contains both a local `TaxCode` and a public `StandardTaxCode`, and Erhvervsstyrelsen publishes the public VAT-code list alongside the standard chart of accounts.

## Decision

Dolibarr DK keeps the two concepts separate:

```text
Dolibarr c_tva.code
       |
       v
llx_dk_vat_mapping
       |
       v
llx_dk_standard_vat_code
       |
       v
SAF-T StandardTaxCode
```

The initial target list version is `20260101`, corresponding to the official 2026-01-01 VAT-code list.

Mappings are effective-dated and may not overlap for the same Dolibarr VAT code.

## Consequences

- no standard VAT code is inferred from percentage alone,
- a 25% local code is not automatically assumed to be `S1`,
- SAF-T strict export fails when a used/configured local VAT code has no valid mapping,
- updates to the public VAT-code list are imported as a new version rather than overwriting historical mappings.
