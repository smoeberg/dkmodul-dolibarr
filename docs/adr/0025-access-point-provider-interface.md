# ADR-0025: Access Point provider interface

**Status:** Proposed for implementation  
**Date:** 2026-10-08  
**Related:** ADR-0024 (accepted provider-agnostic access-point architecture)

## Decision

Implement a versioned, provider-neutral `DkAccessPointProvider` contract and neutral value objects. Provider adapters are the only layer allowed to know provider-specific API paths, authentication, field names, status codes and onboarding mechanics.

The first concrete adapter target is Inexchange, subject to the three commercial/technical clarifications listed below. Maventa remains a viable second provider and must fit the same contract rather than defining it.

## Core contract

Conceptual interface:

```text
DkAccessPointProvider
  capabilities() -> DkAccessPointCapabilities
  health() -> DkAccessPointHealth
  connect(DkAccessPointConnection) -> DkAccessPointConnectionResult
  disconnect() -> DkAccessPointConnectionResult
  registerCompany(DkAccessPointCompany) -> DkAccessPointRegistrationResult
  registrationStatus(registrationId) -> DkAccessPointRegistrationStatus
  createClientToken(request) -> DkAccessPointTokenResult
  revokeClientToken(tokenReference) -> DkAccessPointTokenResult

  send(DkAccessPointOutboundDocument) -> DkAccessPointMessageResult
  outboundStatus(DkAccessPointMessageReference) -> DkAccessPointMessageStatus
  listOutbound(DkAccessPointPollCursor) -> DkAccessPointMessagePage

  listInbound(DkAccessPointPollCursor) -> DkAccessPointMessagePage
  downloadInbound(DkAccessPointMessageReference) -> DkAccessPointDocument
  markInboundHandled(DkAccessPointMessageReference) -> DkAccessPointHandleResult
```

The exact PHP method names may be refined during implementation, but the semantic boundary must remain stable.

## Neutral value objects

Core objects must describe business meaning, not transport-provider vocabulary:

- `DkAccessPointConnection`: provider adapter id plus encrypted/provider-declared connection configuration.
- `DkAccessPointCapabilities`: supported document types, directions, networks, status/receipt support, polling/webhook support and onboarding capabilities.
- `DkAccessPointCompany`: legal identity and neutral participant/address information.
- `DkAccessPointOutboundDocument`: dkmodul document id, document format, payload/file reference, recipient identity and idempotency/reference data.
- `DkAccessPointMessageResult`: neutral provider reference, dkmodul reference, accepted/queued result and transport metadata.
- `DkAccessPointMessageStatus`: Pending/Sent/Delivered/Failed/Stopped-style normalized state plus provider status retained only as adapter metadata.
- `DkAccessPointMessagePage`: cursor, timestamps and neutral message references.
- `DkAccessPointDocument`: payload, detected/declared format, attachments and source metadata.
- `DkAccessPointHealth`: connectivity, authentication and service state.

Provider-specific fields belong in adapter configuration or opaque metadata; they must not leak into the core contract.

## Inexchange mapping

The initial adapter maps the documented flow approximately as:

1. API key + ClientToken authentication -> `DkAccessPointConnection`.
2. `POST /companies/register` -> `registerCompany()`.
3. `GET /companies/status` -> `registrationStatus()`.
4. Client-token create/revoke endpoints -> token lifecycle methods.
5. Upload -> send -> status/by-ErpDocumentId -> `send()`, `outboundStatus()`, `listOutbound()`.
6. `GET /documents/incoming` -> `listInbound()`.
7. Download + `POST /documents/handled` -> `downloadInbound()` + `markInboundHandled()`.
8. Provider polling is the baseline event mechanism; no webhook dependency is assumed.

The adapter must preserve dkmodul's own document id as the stable correlation key.

## Maventa mapping

Maventa also fits the contract:

- OAuth2 company authentication maps into `DkAccessPointConnection`.
- Company/settings APIs cover company setup and network activation.
- REST invoice/document APIs cover upload, send, receive, file download and delivery events.
- Maventa supports webhooks as well as polling; the core must therefore expose webhook capability without making webhooks mandatory.
- Maventa's modern REST API is the target; its SOAP API is legacy and is not part of the P0 adapter contract.

## Security rules

1. Credentials are never stored in plaintext configuration.
2. Provider configuration is encrypted/secret-backed by Dolibarr.
3. Core code never branches on provider names.
4. Provider adapters own authentication, retries, rate limits and HTTP/API error translation.
5. Audit trail records normalized transport events and the provider reference where available.
6. Inbound documents enter the existing staging boundary; automatic posting is outside P0.
7. Idempotency and duplicate protection must use dkmodul document identifiers where the provider permits it.
8. Provider-specific status codes are retained as diagnostic metadata but never become core state values.

## P0 acceptance criteria

The interface is ready when:

- a fake provider can exercise all core tests without a real provider;
- Inexchange can be plugged in without changes to core business logic;
- a second adapter can be added without changing the interface;
- settings can persist provider-declared configuration and encrypted credentials;
- outbound and inbound flows have deterministic correlation and retry semantics;
- audit events are provider-neutral;
- webhook support is optional capability, not a core dependency.

## Open Inexchange questions

Before production commitment:

1. Exact accepted `DocumentFormat` enum values for OIOUBL 2.02 and Peppol BIS Billing 3.0, including whether Inexchange transforms already-valid documents.
2. Whether company-specific NemHandel/Peppol receiving registration is fully automatable through the API, including CVR/participant activation, or requires portal/support action.
3. Partner-API pricing, contractual terms, DPA/data-processing terms and governing law for the intended Danish customer/partner setup.

## Consequence

We can start the interface and fake-adapter tests now. Inexchange can be the first implementation target without making the core architecture Inexchange-specific. Maventa remains a natural second adapter and a useful portability test.
