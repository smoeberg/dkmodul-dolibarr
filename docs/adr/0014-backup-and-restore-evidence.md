# ADR-0014 — Backup and restore evidence

Status: Accepted

## Context

The digital bookkeeping control trail is incomplete if database and document
archive backups cannot be tied to a verifiable backup artifact and a recorded
restore test. The application must not claim that a backup platform performed
these operations merely because a backup configuration exists.

## Decision

The production profile records backup/restore evidence through
`DkBackupEvidenceService`.

Each evidence record contains:

- a backup identifier and protected scope,
- artifact byte size and SHA-256 fingerprint,
- UTC completion timestamp,
- restore-test identifier and explicit pass/fail result,
- the actor recording the evidence, and
- an evidence SHA-256 over the canonical JSON record.

Evidence files are content-addressed and created with an atomic
create-if-absent filesystem operation. Existing evidence is never overwritten.
Verification recalculates the evidence fingerprint and fails closed on malformed
or tampered JSON.

The service deliberately does **not** execute a backup or restore operation.
The deployment backup platform remains responsible for producing the artifact
and running the restore test; it must supply the recorded facts to this
evidence boundary.

A failed restore test is valid evidence of a failed control, not a successful
backup. Release/compliance automation must therefore require
`restoreStatus=passed` for a production evidence claim.

## Consequence

The application has a concrete, testable evidence contract without coupling
the certified code to a specific backup vendor. Backup-platform integration and
key/custody controls remain separate operational gates.
