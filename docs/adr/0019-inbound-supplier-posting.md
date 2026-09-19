# ADR-0019 — Controlled inbound supplier posting

## Status

Accepted.

## Context

ADR-0018 validates the supplier invoice as a business document but deliberately
does not transfer it to the general ledger. Posting is only safe when the
purchase, purchase-VAT and supplier payable accounts are explicit and the whole
movement can be proven balanced before it becomes immutable.

## Decision

The posting action accepts only an invoice linked to immutable DK validation
evidence. It requires an authenticated Advanced Accounting writer and an
explicitly selected active purchase journal.

Mappings come from Dolibarr's native accounting configuration:

- each supplier-invoice line uses `fk_code_ventilation` for its purchase account;
- each non-zero `vat_src_code` resolves through exactly one active Danish VAT
  rule and its `accountancy_code_buy`;
- the supplier provides `accountancy_code_supplier_general` and
  `code_compta_fournisseur`.

Missing or ambiguous mappings, local taxes, non-DKK currency, non-positive
amounts and pre-existing partial transfers are rejected before any line is
created. Entries are aggregated by account, checked in integer øre, created via
Dolibarr 24 `BookKeeping::createStd()`, re-read and checked for one piece number
and exact balance, then locked with `date_validated` inside the same outer
transaction. Posting evidence and its audit event are append-only.

## Consequences

- a failed control rolls back the entire movement;
- exact retry is idempotent and verifies the locked movement;
- validated bookkeeping is protected by the existing database immutability
  guard;
- credit notes, foreign currency, local taxes and the user-facing workflow remain
  outside this slice, so `DK-EINV-002` remains `PARTIAL`.
