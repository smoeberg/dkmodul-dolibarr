# ADR-0018 — Inbound supplier invoice validation boundary

## Status

Accepted.

## Context

ADR-0017 ends with a Dolibarr supplier invoice in draft state. Document validation
is irreversible business approval, but it is not the same action as transferring
the invoice to the general ledger. Combining both actions would make it difficult
to prove actor authority, source integrity and complete account mapping before a
posting is created.

## Decision

A second explicit action validates the draft through Dolibarr 24
`FactureFournisseur::validate()` with triggers enabled. Immediately beforehand it
locks and verifies the immutable inbound link, archived source hash, supplier CVR,
supplier reference, payable total, entity and draft status. The authenticated actor
must match the recorded actor ID and hold supplier-invoice validation permission.

Successful validation creates append-only evidence and a hash-chained audit event.
An exact retry returns the existing evidence. A previously validated invoice without
that evidence is rejected instead of being adopted silently.

Validation must not create `accounting_bookkeeping` rows. General-ledger transfer is
a later controlled action and requires complete purchase, supplier and VAT account
mapping plus a balanced-transaction check.

## Consequences

- receipt, technical validation, draft creation, business validation and ledger
  transfer remain separately auditable actions;
- Dolibarr remains the authority for supplier-invoice state transitions;
- `DK-EINV-002` remains `PARTIAL` until controlled ledger transfer and the user-facing
  workflow are implemented.
