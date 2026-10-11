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


## Compliance-mode import policy

**Decision for the registered P0 profile:** the generic Dolibarr accounting import must be unavailable while DK compliance mode is enabled until a controlled importer is implemented and approved. The generic importer can write directly to `accounting_bookkeeping`, including validation state, without the DK actor/audit/correction semantics; documenting the risk is not sufficient mitigation.

The controlled importer must, at minimum:

1. reject attempts to import already-validated/posting-locked rows;
2. preserve the authenticated actor and source/import identity;
3. validate account, VAT, period, and transaction-balancing rules before persistence;
4. write ledger rows and corresponding append-only audit evidence atomically, rolling back on audit failure;
5. make retries idempotent and record a stable source hash/import identifier;
6. emit evidence for rejected rows without persisting them as accepted postings;
7. be covered by negative tests for trigger bypass, forged validation state, duplicate retries, and audit-write failure.

**Implementation status: OPEN.** This policy is not considered enforced until the compliance-mode UI/entry point is actually disabled and integration-tested. Do not mark DK-ACC/SAF-T import coverage complete based on this decision alone.

## Upgrade gate

For every supported Dolibarr release:

1. diff the upstream `accounting_bookkeeping` table definition;
2. repeat code search for INSERT/UPDATE/DELETE paths;
3. classify every new column;
4. run DB guard tests;
5. run application integration tests;
6. approve the version before production deployment.


## Controlled deletion policy for bookkeeping

**Status: design requirement; implementation and integration evidence are still open.** The normal Dolibarr delete methods are not interchangeable: `deleteMvtNum()` emits the application trigger unless `$notrigger` is set, while `deleteByImportkey()` and `deleteByYearAndJournal()` use direct SQL and do not provide equivalent per-row trigger evidence.

### Required enforcement

1. Keep a database `BEFORE DELETE` guard on `accounting_bookkeeping`. It must reject every delete where `date_validated IS NOT NULL`, regardless of the caller or trigger setting.
2. Fail closed for unvalidated rows too, unless the operation uses an explicit, guard-visible controlled-delete context. The context must be transaction-scoped and protected against stale/reused authorization; ordinary direct SQL must not be able to self-authorize by supplying an arbitrary user variable or payload.
3. Expose one DK-owned delete service as the supported deletion path. It must resolve the exact target rows/import key/year+journal, capture the authenticated Dolibarr actor and row-level before-state, append `bookkeeping.deleted` audit events using `DkAuditLedger`, and perform the deletes in the same database transaction. Audit-write or delete failure must roll back the whole operation.
4. Route controlled deletion through one implementation that does not double-emit or omit per-row events. Do not have a database trigger manufacture rows in `dk_audit_event`: the trigger does not possess trustworthy application actor identity and must not reimplement the ledger's canonical JSON/hash-chain algorithm.
5. `deleteByImportkey()` and `deleteByYearAndJournal()` must be rejected by the DB guard when called as raw direct-SQL paths; callers in compliance mode must use the DK service. Any integration with upstream Dolibarr methods must be verified against the exact supported core version.

### Required negative integration evidence

- Separate case for `deleteMvtNum()`: a validated row remains unchanged, the DB guard is the rejecting control, and existing audit evidence remains verifiable. Do not count a core pre-filter alone as proof that the DB guard fired.
- Separate raw `deleteByImportkey()` case (and `deleteByYearAndJournal()`): direct path is rejected by the DB guard.
- Controlled-wrapper case: an unvalidated target is deleted only when its per-row `bookkeeping.deleted` event is committed with the authenticated actor ID and target row ID; audit failure or delete failure leaves both ledger and audit state unchanged.
- Trigger-bypass case: `$notrigger = 1` does not bypass DB protection.
- Forged-validation case: attempts to set or tamper with `date_validated` cannot authorize a delete or modify a validated row.
- Audit-failure case: mutation is rejected/rolled back when the audit append fails.
- Append-only case: UPDATE and DELETE attempts against the committed audit event are rejected, and `DkAuditLedger::verifyChain()` still verifies the chain.

Do not mark `audit-write-paths` complete until these tests prove the database rejection and application evidence independently. A row merely surviving a core API call is not enough to prove the database guard fired.
