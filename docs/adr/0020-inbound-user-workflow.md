# ADR-0020 — Inbound OIOUBL user workflow

## Status

Accepted.

## Context

ADR-0016 through ADR-0019 establish four separately controlled write paths for
inbound invoices. Operators need one view of their progression without replacing
the authorization, validation, transaction and idempotency boundaries in those
services.

## Decision

The module exposes a read model whose state is derived from immutable evidence:

`received` → `validated` → `draft` → `supplier_validated` → `posted`.

Technical rejection is terminal as `rejected`. The view never stores or permits
clients to submit a state value. Each transition is a POST-only action protected
by Dolibarr's CSRF token and re-reads the current server-side state before calling
the existing domain service.

Access is divided into module rights for read, draft approval, supplier-invoice
validation and ledger posting. Core Dolibarr supplier-invoice and Advanced
Accounting rights remain additional requirements. Posting requires an explicit
active purchase journal; supplier identity remains derived from the validated CVR.

## Consequences

- no UI action can skip an evidence link or manufacture a workflow state;
- retries retain the idempotency behavior of the underlying services;
- organizations can assign approval, validation and posting to different users;
- technical XSD/Schematron intake remains an integration responsibility;
- credit notes, foreign currency and local taxes remain separate slices.
