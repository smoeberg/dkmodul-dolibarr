# ADR-0016 — Signing and custody boundary

Status: Accepted

## Context

Operational evidence must be capable of being handed to an external signing and key-custody system without embedding private keys or vendor-specific custody code in the bookkeeping application.

## Decision

`DkEvidenceSigner` defines the application boundary for signing an evidence record. A production implementation receives the evidence and returns an algorithm, external key identifier, and signature value. Private keys and key custody are explicitly outside the application boundary.

`DkCustodyEvidence` validates the returned envelope before attaching it to evidence. Missing, empty, or oversized signature metadata is rejected. The application does not claim that a signature proves custody merely because an envelope exists; production compliance evidence requires a real signer/custody integration and independently verifiable key configuration.

## Consequence

The certified code has a deterministic, testable interface for external signing while avoiding secret material in the application. Deployment must provide the actual cryptographic provider, protected key storage, rotation policy, and verification procedure.
