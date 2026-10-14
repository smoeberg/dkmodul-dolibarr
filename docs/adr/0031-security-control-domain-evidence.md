# ADR-0031: Explicit security control-domain evidence

## Status

Accepted.

## Decision

Signed P0 security-risk evidence must explicitly cover every production security control domain that the release gate treats as mandatory:

- IAM
- network
- supplier
- logging
- incident
- data protection

Each domain is represented by an approved status and a SHA-256 fingerprint of its supporting evidence. The signed payload is rejected if a mandatory domain is missing, has an invalid fingerprint, is not approved, or if an unsupported domain is supplied.

This is an application-side evidence contract. The application does not fabricate, generate, or independently approve the underlying operational artifacts. The referenced evidence remains an external production responsibility and is indexed by its SHA-256 fingerprint.

## Rationale

A single aggregate `controls_sha256` does not prove that every P0 security domain has been considered. Requiring explicit domains makes the signed assessment structurally auditable and fail closed when a mandatory area is absent.

The evidence remains deployment-scoped and signed, so the runtime verifies the trust boundary before accepting the control-domain assertions.

## Consequences

- Missing IAM, network, supplier, logging, incident, or data-protection evidence blocks the signed risk assessment.
- Each domain must have a canonical SHA-256 fingerprint.
- The implementation does not claim that the referenced production controls are actually deployed; those artifacts and their approval remain deployment evidence.
