# Accounting compliance architecture

## Current Dolibarr behaviour

Dolibarr Advanced Accounting stores final bookkeeping rows in `accounting_bookkeeping`. Current core code exposes `BOOKKEEPING_CREATE`, `BOOKKEEPING_MODIFY` and `BOOKKEEPING_DELETE` triggers.

Validated rows carry `date_validated`. Core already uses that state to lock validated movements in several workflows.

## Dolibarr DK first control layer

```text
BookKeeping create/modify/delete
        |
        v
Dolibarr trigger transaction
        |
        v
DK PostingGuard
   |           |
 unlocked     locked
   |           |
 continue     return -1
               |
               v
          DB rollback
```

Created entries also emit an event into `dk_audit_event`.

## Why this is only the first slice

An application trigger is not equivalent to a database immutability guarantee:

- Dolibarr methods may allow callers to disable triggers.
- privileged direct SQL access bypasses PHP.
- some bulk accounting operations must be checked separately.

Therefore the trigger implementation is a working integration control, not yet the final certification proof.

## Next proof work

1. inventory every write path to `accounting_bookkeeping`,
2. identify all paths using `$notrigger`,
3. exercise validated-entry mutation attempts in a real Dolibarr test environment,
4. decide between:
   - application-only enforcement plus restricted DB privileges,
   - database trigger/permissions,
   - upstream Dolibarr core hardening,
5. add end-to-end compliance tests and only then mark DK-ACC-006/007 DONE.
