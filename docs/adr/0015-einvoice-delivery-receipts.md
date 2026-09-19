# ADR-0015 — E-invoice delivery and receipt boundary

Status: Accepted

## Context

Officially valid OIOUBL bytes are immutable source evidence, but generation is
not delivery. Provider APIs, endpoint lookup, retry policies and receipt formats
vary independently and must not leak into the invoice mapper.

## Decision

An immutable delivery row identifies one archived OIOUBL document, recipient
endpoint and transport using a stable SHA-256 idempotency key. All attempts and
provider outcomes are append-only transport events. Current state is derived
from the latest outcome; accepted deliveries are never sent again.

`DkTransportAdapterInterface` receives the exact archived bytes plus their hash.
The transport service verifies size and SHA-256 before calling an adapter. The
adapter returns a normalized accepted, rejected or failed receipt. Its canonical
JSON and SHA-256 are stored as immutable evidence and linked into AuditLedger.

The database delivery row is locked while an adapter call is active. This gives
at-most-one concurrent attempt per delivery in the certified MariaDB profile.

## Consequences

The deterministic fake adapter used in CI proves the boundary, state handling
and evidence chain, but is not a live NemHandel/Peppol connector. Provider
authentication, discovery, HTTP policy and production operational monitoring
need a separate adapter and deployment decision. `DK-EINV-001` remains PARTIAL.
