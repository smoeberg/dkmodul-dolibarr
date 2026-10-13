# ADR 0021 — P0 release signing approval gate

## Status

Accepted.

## Decision

The P0 release collector must distinguish a cryptographically usable signing key
from an approved production release key.

For a registered-candidate product, the configured signing provider is accepted
only when both the key identifier and the SHA-256 fingerprint of its public key
match the release-signing values in the trusted product manifest. A report
signature is then self-verified against the same provider before the signed
report is persisted.

A mismatched, missing, or invalid key must not be used to produce a production
release signature. The release gate remains BLOCKED instead.

## Evidence boundary

The policy proves the application-side binding between the configured signer
and the approved manifest identity. It does not prove that the operational key
ceremony, custody, owner approval, rotation or revocation controls actually
occurred. Those remain production evidence requirements.

## Consequence

A test/private key cannot silently become an approved production signer merely
because it can produce a valid RSA signature.
