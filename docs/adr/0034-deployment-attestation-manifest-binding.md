# ADR-0034: Bind deployment attestation to production evidence manifest

## Status

Accepted.

## Context

The registered profile verifies the signed deployment attestation, while the
production evidence manifest independently records an evidence reference and
SHA-256 fingerprint for the deployment-attestation control. Independent
validation is insufficient if the manifest can point at a different attestation.

## Decision

At the registered-candidate release boundary, the application loads the same
signed deployment attestation used by the registered runtime and computes its
canonical application evidence fingerprint. The production evidence manifest's
`production-deployment-attestation` item must be `evidenced`, contain a valid
SHA-256 fingerprint, and match that fingerprint. The deployment id must also
match the release deployment id.

A mismatch, missing/open control, invalid fingerprint, or deployment mismatch
fails closed.

The application does not fabricate the signed attestation or its production
approval. The manifest remains an index of externally governed evidence.

## Consequence

The release boundary now has one cryptographic binding between the signed
deployment attestation and the deployment-scoped production evidence manifest,
preventing evidence substitution between deployments or attestations.
