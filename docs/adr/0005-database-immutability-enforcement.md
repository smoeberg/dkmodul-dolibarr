# ADR-0005 — Database-level immutability enforcement

Status: Accepted

## Context

Dolibarr exposes application triggers for bookkeeping writes, but application-level enforcement can be bypassed by:

- calls with `$notrigger = 1`,
- direct SQL from privileged code,
- administrative SQL access.

The Danish compliance requirement concerns the integrity of recorded bookkeeping data, not only the behaviour of one UI path.

## Decision

The initial managed MariaDB product profile uses database BEFORE UPDATE and BEFORE DELETE triggers on `accounting_bookkeeping`.

Once `date_validated` is set:

- protected financial fields cannot change,
- the row cannot be deleted,
- operational metadata that does not alter the economic substance may still be updated.

Application-level PostingGuard and audit logging remain in place as the first control layer.

## Protected vs mutable data

Protected examples:

- document/accounting date,
- journal,
- account/subledger,
- debit/credit/amount,
- document reference,
- piece/ref identifiers.

Mutable operational examples:

- export timestamp,
- allowed matching/lettering metadata,
- technical modification timestamp.

The exact field classification is version-specific and must be tested against every supported Dolibarr release.

## Consequences

- MariaDB is part of the first certified runtime profile.
- DB trigger existence and integrity must become a compliance health check.
- database migration/admin roles must be separated from the runtime application role.
- changes to protected-field classification require an ADR/migration and regression test.
- a future PostgreSQL or other DB profile requires equivalent enforcement and separate certification evidence.
