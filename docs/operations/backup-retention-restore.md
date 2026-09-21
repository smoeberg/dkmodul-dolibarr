# Backup, retention and restore control baseline

Status: P0 draft — provider evidence required before `registered-candidate`.

## Required control objectives

| Control | Minimum | Evidence required |
|---|---|---|
| Full backup | Automatically at least weekly | Provider job configuration, immutable job log and monthly success report |
| Incremental backup | Automatically at least daily | Provider job configuration, immutable job log and daily failure alert |
| Location | At least one full and incremental copy in EU/EØS | Named provider, service, region and contract/data-location evidence |
| Retention | Transactions and covered vouchers for five years from financial-year end | Object-lock/lifecycle policy and test proving early deletion is denied |
| Customer exit | Retention continues after termination, bankruptcy or compulsory dissolution | Exit runbook, ownership/RACI and tested authority-access path |
| Encryption | At rest and in transit, with recoverable keys | Key inventory, rotation policy, escrow/recovery test and access log |
| Restore | Data and documents are readable and reconcilable | Quarterly restore test with hashes, row counts, document sampling and sign-off |

## Restore acceptance test

1. Select a production-like tenant and a recovery point without exposing personal data.
2. Restore database, document objects, encryption keys and product manifest into an isolated EU/EØS recovery environment.
3. Verify database migration level and every required DK database guard.
4. Verify the audit-ledger chain, document SHA-256 hashes and bookkeeping balances.
5. Generate SAF-T for a selected period and open a sample of purchase and sales vouchers.
6. Record RPO, RTO, exceptions, operator, reviewer and immutable evidence references.
7. Destroy the recovery environment under dual control after evidence is retained.

## Release gate

The profile must not be changed to `registered-candidate` until all `TBD` deployment values in `dkmodul/product-manifest.json` are resolved and a successful restore test is referenced there.
