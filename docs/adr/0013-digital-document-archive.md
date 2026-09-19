# ADR-0013 — Immutable digital document archive

Status: Accepted

## Context

Bookkeeping entries must have a discoverable control trail to their evidence,
and relevant accounting material must remain available throughout its retention
period. A path to a mutable Dolibarr-generated file is not sufficient evidence:
the bytes at that path can be replaced without changing the bookkeeping row.

## Decision

Dolibarr DK archives evidence through `DkDocumentArchiveService` before treating
it as retained bookkeeping material.

The service:

- requires an existing bookkeeping row in the same entity,
- calculates SHA-256 and byte size from the source bytes,
- copies the bytes to an entity/year-scoped, content-addressed archive,
- records source identity, original name, MIME type and actor,
- derives `retain_until` as five years after the fiscal-year end,
- appends a `document.archived` event to the hash-chained audit ledger, and
- can re-read the archived bytes and verify hash and size.

Archive metadata is append-only through MariaDB triggers. Stored files are
created read-only. The initial profile deliberately provides no deletion API;
a future controlled disposal workflow must prove expiry, authorization and an
audit event before deletion is introduced.

## Boundary

The archive service uses a filesystem root supplied by the managed runtime.
Deployment backup, restore and geographic redundancy remain runtime controls
and are not claimed by this code slice.

OIOUBL and Peppol payloads will use this same archive as their immutable source
document. Format validation, transport receipts and message-state handling are
separate e-invoice concerns.
