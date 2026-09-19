# Accounting corrections

Dolibarr DK does not edit a validated accounting movement to correct an error.

The target correction model is:

```text
original validated movement
        |
        v
correction movement(s)
        |
        v
dk_correction
        |
        v
audit event
```

## Compliance relation

`dk_correction` records one typed relation per correction movement:

- original piece number,
- correction piece number,
- relation type (`reversal`, `adjustment` or `replacement`),
- correction reason,
- author,
- timestamp.

`DkCorrectionService::reverse()` locks and reads the validated original, creates
new opposite debit/credit rows through Dolibarr's `BookKeeping` API, validates
the new piece, persists the relation and appends the audit event in one outer
database transaction. `record()` registers an already-created adjustment or
replacement through the same relation and audit model.

## Rules

- original must remain immutable,
- reversal is a new normal accounting movement,
- reversal must negate the original economic values,
- replacement, if any, is a separate new movement,
- reason is mandatory,
- relation creation is audit logged,
- a piece can only be reversed once through the controlled workflow.
