# SAF-T 2.1 import architecture

## Source of truth

The importer targets the same pinned ERST SAF-T 2.1 XSD as the exporter.

Before any import data is accepted, the XML must validate against the official schema.

## Parsing model

The importer converts relevant SAF-T structures into a version-neutral import package:

- Header/company/selection metadata,
- General Ledger accounts and StandardAccountID,
- VAT TaxTable,
- Journals,
- Transactions,
- Lines,
- TaxInformation.

GeneralLedgerEntries are represented using the existing canonical transaction/line model. This means balance invariants are shared between export and import.

## Integrity gates

The importer rejects:

- non-XSD-valid XML,
- versions other than the supported Danish 2.1 profile,
- non-DK AuditFileCountry,
- duplicate AccountID,
- duplicate TransactionID,
- duplicate RecordID within a transaction,
- transactions with fewer than two lines,
- unbalanced transactions,
- mismatch between declared NumberOfEntries and actual lines,
- mismatch between declared TotalDebit/TotalCredit and parsed values.

## Staging

A successful parse may be staged. Staging retains the file SHA-256 and normalized accounting structures. It is intentionally separate from the production ledger.

A unique constraint on `entity + source_hash` prevents the same exact file being staged twice.

## Account resolution

Before Apply, every source AccountID must resolve to one local account.

Resolution order:

1. exact active Dolibarr account-number match,
2. a unique effective-dated mapping from imported StandardAccountID to a local account.

Zero candidates => unresolved.

More than one candidate => ambiguous.

Both states block Apply.

## Apply design

Apply will be implemented as an explicit transactional service. It must:

- use supported Dolibarr bookkeeping APIs,
- allocate local piece numbers independently of external TransactionID,
- preserve external TransactionID/import batch provenance,
- use DK audit/provenance controls,
- reject a second Apply,
- never mutate an existing validated entry to satisfy imported data.

The first Apply implementation will import GeneralLedgerEntries only. Re-creation of source invoices/documents is a separate capability.
