# ADR-0030 — Access-point certificate operations evidence

## Status
Accepted.

## Decision
A registered deployment must carry explicit certificate-operations evidence alongside its
NemHandel/Peppol access-point certification lifecycle evidence. The operations evidence
records the certificate serial, issuer, renewal and revocation contacts, the last
rotation timestamp, the last revocation-test timestamp and an evidence reference.

The evidence is carried inside the signed deployment attestation, so the application
validates the operations contract only after the attestation signature and trust-store
pin have been verified.

Timestamps are canonical UTC values and future operational events are rejected.

## Consequence
The application can fail closed when certificate operations are undocumented,
malformed or temporally impossible. Actual certificate issuance, rotation, revocation
testing and authority records remain production evidence and are not fabricated by the
application.
