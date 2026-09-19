# ADR-0012 — SAF-T 2.1 import uses validate → stage → analyze → apply

Status: Accepted

## Context

Erhvervsstyrelsen requires registered bookkeeping systems to support generation, import and export of SAF-T. Importing an external accounting file is a high-integrity operation: the file may contain invalid totals, duplicate identifiers, unknown accounts or data already imported earlier.

Writing XML directly into `accounting_bookkeeping` would bypass the review/mapping step and make accidental duplicate imports difficult to prevent.

## Decision

SAF-T 2.1 import is a four-gate workflow:

```text
uploaded SAF-T XML
       |
       v
official ERST XSD validation
       |
       v
canonical import package
       |
       +-- declared totals vs parsed totals
       +-- balanced transactions
       +-- unique TransactionID / RecordID
       |
       v
staging tables
       |
       +-- source SHA-256 duplicate guard
       +-- imported account catalogue
       +-- imported VAT catalogue
       +-- transactions and lines
       |
       v
mapping analysis
       |
       +-- exact local account match
       +-- or unique effective-dated StandardAccountID mapping
       +-- unresolved/ambiguous => BLOCK
       |
       v
explicit Apply
       |
       v
Dolibarr bookkeeping API + DK provenance controls
```

## Current implementation scope

Implemented:

- official XSD validation before parsing,
- Danish SAF-T 2.1/country checks,
- Header, GeneralLedgerAccounts, TaxTable and GeneralLedgerEntries parsing,
- canonical balanced-transaction validation,
- declared entry/debit/credit total verification,
- duplicate transaction/line ID rejection,
- staging tables,
- exact-file SHA-256 duplicate protection,
- account mapping analysis.

Not yet implemented:

- final Apply/posting into Dolibarr,
- applied-import rollback strategy (corrections must follow normal immutable accounting rules),
- UI for preview/mapping/approval,
- full source-document recreation.

## Consequences

- `DK-SAFT-002` remains PARTIAL until Apply is implemented and integration-tested.
- a staged file is not bookkeeping yet.
- the same exact file cannot be staged twice for the same entity.
- ambiguous account mappings block Apply rather than choosing a local account automatically.
- imported transaction identifiers are retained separately from Dolibarr's local piece numbering.
