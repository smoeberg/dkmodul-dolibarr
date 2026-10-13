# ADR-0023 — P0 release signing key ceremony evidence

## Status

Accepted.

## Decision

The registered-candidate release gate requires signed evidence that the configured production
P0 release signing key was independently approved and placed under controlled custody.

The evidence binds the approved key identifier and public-key SHA-256 fingerprint to the product
manifest and records owner, independent approver, ceremony date, custody reference, rotation
policy, revocation policy and review date.

The application verifies the evidence signature against the existing attestation trust store and
fails closed when the evidence is missing, malformed, stale, untrusted or inconsistent with the
manifest. Private key material is never stored in the evidence contract.

## Evidence boundary

The application proves the cryptographic evidence contract. Actual production ceremony, HSM/key
custody and independent approval remain operational evidence and must be completed outside the
repository before a production release can be presented as registered.

## Consequence

A valid release signature cannot by itself satisfy the P0 key-approval requirement. The approved
key and its lifecycle evidence must match the manifest before the release signing path is enabled.
