# ADR-0005 — Database-level immutability guard

Status: Accepted

## Context

Dolibarr exposes BOOKKEEPING_CREATE, BOOKKEEPING_MODIFY and BOOKKEEPING_DELETE triggers, but the write-path inventory shows additional direct SQL writes against `accounting_bookkeeping`, including validation, matching/lettering, cloning and standard import paths.

Application triggers therefore cannot be the sole immutability boundary.

## Decision

For the first certifiable Dolibarr DK release, immutable validated bookkeeping is enforced at the database layer in addition to PHP-level controls.

The supported database profile is MariaDB/MySQL-compatible.

Two database triggers are installed:

1. BEFORE UPDATE: once `date_validated` is non-null, compliance-relevant bookkeeping fields cannot change.
2. BEFORE DELETE: a row with non-null `date_validated` cannot be deleted.

`date_export` and the automatic `tms` timestamp remain mutable after validation because exporting a locked entry is operational metadata rather than alteration of the bookkeeping entry itself.

## Consequences

- application code, `$notrigger = 1`, generic imports and privileged SQL all hit the same immutability boundary;
- the production DB user/install role must have permission to create/drop triggers during controlled deployment;
- PostgreSQL is outside the initial registered product boundary;
- schema changes in future Dolibarr versions require an explicit guard-compatibility test;
- all columns added to `accounting_bookkeeping` must be classified as immutable or operational metadata before a Dolibarr upgrade is approved.

## Defence in depth

The database guard does not replace the PHP PostingGuard. The PHP layer provides understandable business errors and audit events; the database layer is the final integrity boundary.
