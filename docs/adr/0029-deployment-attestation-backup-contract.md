# ADR-0029: Bind deployment attestation to the canonical backup contract

## Status

Accepted

## Context

The signed deployment attestation is the trust boundary for deployment-specific backup configuration. The backup operational policy now requires canonical schedules and explicit retention/deletion controls:

- weekly full backup syntax: `weekly:<weekday>:HH:MM`;
- daily incremental syntax: `daily:HH:MM[,HH:MM...]`;
- identified platform and destination;
- retention policy `five-years-from-financial-year-end`;
- explicit denial of early deletion.

The deployment-attestation validator previously accepted weaker values such as `weekly` and `daily`, which meant a signed attestation could pass the trust boundary while later failing the stronger operational policy.

## Decision

The application-side deployment-attestation validator must enforce the same backup contract as `DkBackupOperationalPolicy`.

Signed attestations therefore fail closed unless they contain:

- canonical full and incremental schedules;
- backup platform;
- backup destination;
- the exact five-year retention policy;
- `early_delete_denied=true`.

The test suite signs its attestation payloads at runtime with an ephemeral RSA key instead of relying on a static signed fixture. This keeps the signature tests aligned with the current payload contract without embedding a private production key or maintaining an obsolete signature.

## Consequences

A production deployment attestation must be regenerated and re-approved when its backup schedule, platform, destination or retention/deletion controls change.

This ADR proves only the application-side contract and signature verification. Actual scheduler execution, storage-region evidence, provider controls, retention enforcement, customer-exit access and quarterly restore remain deployment evidence and are not fabricated by the application.
