# ADR-0026: Final P0 release-candidate gate

## Status

Accepted as the final application-side release boundary.

## Decision

The product may only be represented as `registered-candidate` when the
canonical P0 report has decision `PASS`, every required gate is present and
`PASS`, and the report carries a valid signature from the configured
approved release key.

The policy verifies the signature against the provider and therefore binds the
status transition to the exact report hash.

## Evidence boundary

This is an application-side gate, not a production approval. Real production
deployment attestations, backup/restore evidence, access-point certification,
security/risk approval, key ceremony approval and other operational evidence
remain mandatory and cannot be fabricated by this policy.
