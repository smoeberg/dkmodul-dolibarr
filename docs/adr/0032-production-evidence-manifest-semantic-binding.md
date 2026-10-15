# ADR-0032: Production evidence manifest semantic binding

## Context

The production evidence manifest is an application-side index of externally produced deployment evidence. Its required control set is security-sensitive: an unknown control identifier must not silently become a parallel or misspelled control, and an approval timestamp must not be accepted from the future.

## Decision

The manifest validator now:

- rejects control identifiers outside the explicit P0 required-control allowlist;
- rejects evidenced items whose approvedAt is not a canonical UTC timestamp or is later than the release/check evaluation time;
- passes the same evaluation time from the P0 release collector into manifest validation;
- keeps the JSON schema aligned with the runtime allowlist.

The manifest still does not create or verify the external evidence artifact itself. The evidence reference, SHA-256 fingerprint and approval remain deployment evidence.

## Consequences

A production evidence manifest cannot satisfy the application-side contract by inventing a new control identifier or by presenting a future approval. Production operators must still provide and independently approve the underlying evidence artifacts.
