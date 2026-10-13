# ADR-0020 — Quarterly restore and customer-exit evidence gate

Status: Accepted

## Context

The registered profile must not treat a generic restore receipt or a backup
configuration as proof that the operational restore and customer-exit controls
were actually exercised.

The application already evaluates database restore evidence for freshness,
review, integrity hashes and balanced bookkeeping. The signed deployment
attestation is the authoritative boundary for deployment-specific operational
facts.

## Decision

The signed deployment attestation may carry two explicit operational evidence
contracts under `evidence`:

- `quarterly_restore_test`: a passed end-to-end restore test, linked to a
  backup evidence identifier, with UTC completion/review timestamps, reviewer
  and evidence reference. The runtime rejects missing, failed, malformed,
  future-dated or older-than-92-day evidence.
- `customer_exit_access_test`: a passed customer-exit/insolvency access test
  with a tested access/export reference, SHA-256 fingerprint, reviewer and
  UTC review timestamp. The runtime rejects missing, failed, malformed,
  future-dated or older-than-365-day evidence.

The checks are implemented by `DkBackupOperationalPolicy` and are invoked by
`DkBackupComplianceMonitor` for the registered operational status.

Existing attestation documents that do not contain these new evidence objects
remain parseable, but they fail the operational compliance policy closed. This
avoids silently accepting an old attestation as proof of a newly required
control.

## Consequence

Application code now has an explicit, testable contract for the two previously
deployment-only P0 controls.

This change does not claim that any production deployment has completed either
test. The P0 checklist remains open until actual deployment evidence is signed,
reviewed and independently verified.
