# ADR-0024 — Access point model for Nemhandel/Peppol

Status: Accepted

## Context

`docs/compliance/nemhandel-peppol-access-point.md` (PR #54) identifies the
access point (AP) model as the decision that determines the remaining M5 work.
Erhvervsstyrelsen allows two models: an own approved access point (ISMS
documentation + Peppol AP specifications, no formal certification), or a
third-party AP whose selection and technical setup must be approved by the
company's auditor. dkmodul already owns the document layer (ADR-0014..0023:
canonical model, OIOUBL 2.02 generation, XSD/Schematron validation via
Saxon-HE, immutable archive, inbound staging); transport remains separate
(`DK-EINV-001` PARTIAL).

## Decision

**Multi-provider AP abstraction, provider-agnostic by construction.**

The core defines one internal interface, `DkAccessPointProvider`, and a set of
provider-neutral value objects (connection, message result, message status,
capabilities, health). Provider implementations live behind the interface as
adapters. The core code:

- references **only** the interface and the neutral value objects;
- contains **no** provider names, provider API calls, or provider-specific
  field names — nothing about any AP provider is hardcoded anywhere in the
  dkmodul core;
- selects the provider at runtime from module configuration (the adapter class
  is configuration, not code);
- stores connection configuration as a provider-declared schema persisted
  neutrally (credentials via Dolibarr secret storage / encrypted store, never
  plaintext settings);
- exposes a company-facing settings screen where the AP is chosen and
  connected, tested, and disconnected — the same screen works for every
  provider, including a future own-AP provider.

P0 scope: the interface, **one** provider adapter, the settings GUI with
connection test, outbound invoice with status/receipt/retry, transport events
in the audit trail, and inbound receive-into-staging (ADR-0016 boundary).
Inbound automatic posting stays out of P0. A second adapter is added only when
capacity allows.

A later own access point (Oxalis-based or equivalent) becomes **one more
provider implementation**, not an architectural change. The company's own
e-invoicing endpoint therefore stays provider-neutral: the identity,
participant IDs, document types and receipts are expressed in neutral objects
that every provider adapter must map to.

## Boundary

M1 domain code owns OIOUBL/Peppol BIS generation, VAT mapping, validation,
invoice/credit-note semantics, audit trail and bookkeeping status. The AP layer
owns transport, authentication, certificates, participant lookup, send/receive,
receipts, retries and provider-specific status. The interface is the only seam.

## Consequences

- Provider adapters must be independently conformance-tested; core tests use a
  neutral fake adapter, not a real provider.
- The provider-neutral contract must be versioned; adding fields to value
  objects requires an ADR update.
- The auditor's third-party-AP approval (for the chosen provider) and the
  provider's data-processing agreement remain external prerequisites (M3).
- Post-P0: own AP becomes an additional adapter; the AP-engineering outline
  (architecture, AS4, Peppol PKI/SMP, NemHandel registration, operations,
  ISMS evidence, P0 integration) is recorded as a post-P0 track, not P0 work.
