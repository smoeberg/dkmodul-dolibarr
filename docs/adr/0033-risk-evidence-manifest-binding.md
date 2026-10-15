# ADR-0033: Bind signed risk evidence to the production evidence manifest

## Status

Accepted.

## Context

The signed P0 security-risk evidence already proves the approved risk-register and
security-control fingerprints. The deployment-scoped production evidence manifest
records external production evidence references and SHA-256 fingerprints.

A manifest that is complete but points its `formal-risk-register` or
`security-operational-controls` items at unrelated hashes would otherwise satisfy
both contracts independently without proving that the release boundary is using the
same approved evidence.

## Decision

At the registered-candidate release boundary, when signed security-risk evidence is
loaded successfully, the application must bind:

- `formal-risk-register.evidenceSha256` to the signed
  `risk_register_sha256`;
- `security-operational-controls.evidenceSha256` to the signed
  `controls_sha256`.

Both production-manifest controls must be `evidenced`. A missing, open, malformed,
or mismatched binding fails closed.

The application still does not create or approve the external risk register,
security-control evidence, or production approvals. Those remain deployment and
governance evidence.

## Consequence

The production evidence manifest is no longer merely a complete index: the two
risk-related controls are cryptographically bound to the signed security-risk
assessment consumed by the release gate. This prevents evidence substitution at the
release boundary.
