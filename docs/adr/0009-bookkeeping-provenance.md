# ADR-0009 — Database bookkeeping provenance

Status: Accepted

## Context

Not every Dolibarr bookkeeping INSERT passes through `BOOKKEEPING_CREATE`. Standard import and selected accounting workflows may write directly to `accounting_bookkeeping`.

The bookkeeping table itself contains `fk_user_author`, but Dolibarr DK also needs independent evidence that every ledger row was captured when it entered the final ledger.

## Decision

The MariaDB reference runtime installs an AFTER INSERT trigger on `accounting_bookkeeping`.

For every new row it creates one immutable provenance record containing:

- bookkeeping row id,
- entity and transaction/piece number,
- Dolibarr `fk_user_author`,
- SQL `CURRENT_USER()`,
- capture timestamp,
- SHA-256 snapshot hash of compliance-relevant source fields.

Existing bookkeeping rows at module activation are backfilled into the same table with `origin_type=backfill`. New rows have `origin_type=insert`.

The provenance table has a unique key on bookkeeping row id, so every ledger line has at most one origin record.

## Purpose

This control complements, rather than replaces:

- `fk_user_author` in Dolibarr,
- the application audit ledger,
- immutable validated-row DB guards.

It specifically covers direct INSERT paths that do not emit Dolibarr triggers.

## Upgrade requirement

Whenever the upstream bookkeeping schema changes, the snapshot field list must be reviewed as part of the Dolibarr version gate.
