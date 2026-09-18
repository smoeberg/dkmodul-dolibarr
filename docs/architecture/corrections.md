# Accounting corrections

Dolibarr DK does not edit a validated accounting movement to correct an error.

The target correction model is:

```text
original validated movement
        |
        v
reversal movement
        |
        +---- optional replacement movement
        |
        v
dk_correction_link
        |
        v
audit event
```

## Compliance relation

`dk_correction_link` records:

- original piece number,
- reversal piece number,
- optional replacement piece number,
- correction reason,
- author,
- timestamp.

The actual construction of balanced reversal lines will be implemented in the accounting adapter after the canonical accounting model is in place.

## Rules

- original must remain immutable,
- reversal is a new normal accounting movement,
- reversal must negate the original economic values,
- replacement, if any, is a separate new movement,
- reason is mandatory,
- relation creation is audit logged.
