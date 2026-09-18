# accounting_bookkeeping write-path inventory

Baseline reviewed: Dolibarr 24.0.0 accounting schema/runtime paths, with develop compared for forward changes.

## Runtime write paths

| Path | Write type | Dolibarr trigger | Validated-row protection | DK action |
|---|---|---:|---:|---|
| `BookKeeping::create()` | INSERT | BOOKKEEPING_CREATE unless `$notrigger` | N/A | audit in PHP; DB guard protects after validation |
| `BookKeeping::update()` | UPDATE | BOOKKEEPING_MODIFY unless `$notrigger` | core date/fiscal checks | DB BEFORE UPDATE is final guard |
| `BookKeeping::delete()` | DELETE | BOOKKEEPING_DELETE unless `$notrigger` | core date/fiscal checks | DB BEFORE DELETE is final guard |
| `BookKeeping::deleteMvtNum()` | DELETE | BOOKKEEPING_DELETE unless `$notrigger` | explicit `date_validated IS NULL` | DB guard defence in depth |
| `BookKeeping::deleteByYearAndJournal()` | DELETE | direct SQL | explicit `date_validated IS NULL` | DB guard defence in depth |
| `BookKeeping::deleteByImportkey()` | DELETE | direct SQL | uses core modifiable filter | DB guard defence in depth |
| fiscal-period validation | UPDATE `date_validated` | direct SQL | transition into locked state | explicitly allowed |
| `Lettering` / matching methods | UPDATE matching fields | direct SQL | application checks vary by method | DB guard blocks changes after validation |
| clone accounting movement | direct INSERT | none | creates a new row | future audit coverage required |
| extourne/reversal tooling | new accounting rows | mixed/direct | does not mutate validated original | map into DK correction model |
| standard Dolibarr accounting import | generic direct INSERT | none | can import `date_validated` | restrict/replace in compliance mode |
| upgrade/migration scripts | direct SQL | none | maintenance-only | controlled release process |

## Findings

### F-001 — PHP trigger bypass exists

The core BookKeeping API supports `$notrigger = 1`. A caller can therefore bypass DK's BOOKKEEPING_MODIFY/DELETE trigger.

Resolution: database-level immutable guard.

### F-002 — direct SQL is part of normal Dolibarr accountancy

Lettering/matching, fiscal validation and cloning contain direct SQL against the bookkeeping table.

Resolution: do not assume CommonObject/trigger coverage.

### F-003 — generic accounting import needs a DK policy

Dolibarr's standard import definition writes directly to `accounting_bookkeeping` and can include `date_validated`.

For the registered product, generic direct ledger import must either:

- be disabled in DK compliance mode, or
- route through a controlled DK importer that preserves actor, audit and validation semantics.

Decision remains open and is required before DK-ACC-004/005 and SAF-T import can be marked complete.

### F-004 — clone/reversal inserts need audit coverage

Database immutability protects existing validated rows but does not automatically generate application audit events for new direct INSERTs.

We need one of:

- database-level insert audit,
- core/upstream trigger coverage for all inserts,
- disabling unsupported direct-insert UI flows in compliance mode,
- periodic integrity reconciliation proving every ledger row is represented in the audit ledger.

## Upgrade gate

For every supported Dolibarr release:

1. diff the upstream `accounting_bookkeeping` table definition;
2. repeat code search for INSERT/UPDATE/DELETE paths;
3. classify every new column;
4. run DB guard tests;
5. run application integration tests;
6. approve the version before production deployment.
