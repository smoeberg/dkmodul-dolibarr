# ADR-0023 — Controlled OIOUBL application responses

## Status

Accepted.

## Context

A transport receipt only proves that an outbound invoice or credit note reached a
transport boundary. It does not prove that the recipient technically or
commercially accepted the document. OIOUBL `ApplicationResponse` carries that
separate outcome and must not be allowed to change the immutable delivery record.

## Decision

Inbound OIOUBL `ApplicationResponse` documents are validated against the pinned
official XSD and Schematron before they can affect the derived delivery status.
The response must reference the exact ID, UUID and document type found in the
archived outbound invoice or credit note. Its sender must equal the delivery
recipient and its receiver must equal the supplier endpoint in the archived
document.

The original response bytes are stored content-addressed. Provider message
identity is idempotent; reuse with different bytes is rejected. Response
metadata, validation artefact hashes and binding evidence are recorded in an
append-only table and linked into `AuditLedger`. Transport events and application
responses remain distinct evidence streams.

The accepted OIOUBL response codes are `BusinessAccept`, `BusinessReject`,
`ProfileReject`, `TechnicalAccept` and `TechnicalReject`. Accept/reject state is
derived from the immutable response rather than written back to the delivery.

## Consequences

- a response cannot be attached to a different delivery, endpoint or document;
- retries reuse the original response record;
- database guards prevent response evidence from being changed or deleted;
- outbound response generation and Peppol response formats remain separate scope.
