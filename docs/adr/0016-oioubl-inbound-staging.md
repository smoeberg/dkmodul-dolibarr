# ADR-0016 — Inbound OIOUBL staging boundary

Status: Accepted

## Context

Inbound XML and transport metadata are untrusted. Creating a Dolibarr supplier
invoice or bookkeeping entry before format and business-rule validation would
mix receipt, interpretation, approval and posting into one irreversible action.

## Decision

The first inbound slice stores the exact received bytes in a content-addressed
immutable area. A stable key over entity, channel and provider message ID makes
identical redelivery idempotent; reuse of that identity with different bytes is
rejected as a conflict.

Validation runs against the pinned official UBL 2.1 XSD and OIOUBL Invoice
Schematron used by outbound generation. Saxon executes XSLT 2.0 outside hardened
PHP. PHP verifies its report, then extracts only invoice ID/UUID/date/currency,
supplier/customer endpoints and payable amount. The validation result, official
artefact hashes and errors are stored as one append-only evidence event and
linked to AuditLedger.

## Consequences

Staging and validation never create or modify a supplier invoice, approval or
bookkeeping record. Mapping, counterparty resolution, human approval and posting
form a later slice. `DK-EINV-002` is therefore PARTIAL.
