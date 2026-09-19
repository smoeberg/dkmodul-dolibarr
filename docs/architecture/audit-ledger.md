# Audit ledger integrity

`DkAuditLedger` stores compliance events in an entity-scoped SHA-256 hash chain.
Each event commits its payload hash, metadata hash and predecessor hash into the
event hash.

## Integrity controls

- `BEFORE UPDATE` and `BEFORE DELETE` database triggers make persisted events
  append-only even for direct SQL access.
- `(entity, previous_hash)` is unique, so two concurrent events cannot create a
  fork from the same predecessor.
- the predecessor lookup uses `FOR UPDATE` when the caller has an active
  transaction.
- `verifyChain()` recomputes the chain from the first event and fails on a
  changed link or event hash.

The integration suite proves direct UPDATE and DELETE rejection, fork rejection
and successful end-to-end chain verification on MariaDB 11.4.

These controls establish ledger integrity. `DK-AUD-001` remains `PARTIAL` until
all compliance-relevant application write paths emit the required events and
the operational verification/reporting workflow is defined.
