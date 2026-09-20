# ADR-0021 — Controlled inbound supplier credit notes

## Status

Accepted.

## Context

The inbound OIOUBL flow accepts supplier invoices through technical validation,
business approval, Dolibarr validation and immutable balanced posting. A supplier
credit note must use the same controls while preserving its reference to the
credited invoice and reversing purchase, VAT and payable movements.

## Decision

The inbound boundary accepts the official UBL `CreditNote` document alongside
`Invoice`. Metadata extraction is root-namespace aware, and a credit note must
contain a billing reference that resolves to exactly one validated, non-credit
supplier invoice for the same supplier.

Dolibarr remains the document authority. The draft is created as native
`FactureFournisseur::TYPE_CREDIT_NOTE`, linked through `fk_facture_source`, and
uses positive OIOUBL quantities and prices. Dolibarr converts its lines and totals
to negative values. Validation rechecks document type, source bytes, supplier,
reference and the signed total before calling Dolibarr's standard validation API.

Posting derives direction from the signed Dolibarr values. A credit note debits
the supplier payable account and credits the original purchase and purchase-VAT
accounts. The movement must balance exactly in integer øre and remains subject to
the existing atomic transaction, evidence, audit, locking and idempotency rules.

## Consequences

- invoice and credit-note processing share one monotone workflow and evidence chain;
- an unreferenced or ambiguous supplier credit note is rejected before draft creation;
- retry cannot create a second credit note or a second reversal movement;
- outbound credit notes, aggregate over-credit control, foreign currency and local
  taxes remain outside this slice.
