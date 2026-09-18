# Compliance tests

This directory will contain end-to-end tests that map one-to-one to `COMPLIANCE_MATRIX.md`.

## First vertical slice

Target requirements:

- DK-ACC-006 — validated bookkeeping entries cannot be modified.
- DK-ACC-007 — validated bookkeeping entries cannot be deleted.
- DK-AUD-001 — application audit events form a verifiable append-only hash chain.

The first implementation uses Dolibarr's `BOOKKEEPING_MODIFY` and `BOOKKEEPING_DELETE` triggers. Dolibarr executes the modify trigger inside the same database transaction after the UPDATE; returning a negative value rolls the transaction back. The delete trigger is called before the DELETE.

## Important open gap

Dolibarr APIs can technically be called with triggers disabled (`$notrigger = 1`), and direct database access bypasses application triggers. Before DK-ACC-006/007 can be marked DONE for certification, we must prove that all supported production paths are protected and decide whether a database-level guard or a narrowly scoped core contribution is needed.

This is intentionally tracked as a certification gap rather than hidden behind a test that only proves the happy path.
