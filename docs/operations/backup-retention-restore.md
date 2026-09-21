# Backup, retention and restore control baseline

Status: P0 draft — evidence is supplied per customer deployment, not hardcoded in the module.

## Runtime evidence model

`llx_dk_backup_evidence` records immutable full and incremental backup receipts. `llx_dk_restore_evidence` records immutable quarterly restore results. MariaDB triggers reject updates and deletes on both tables.

`DkBackupComplianceMonitor` fails closed unless it can find:

- a successful full backup no older than eight days,
- a successful incremental backup no older than two days,
- an independently operated EU/EØS copy with a verified receipt,
- retention and immutability dates meeting the required financial-year horizon, and
- a passed restore test no older than 92 days with database, document and SAF-T hashes, reviewer evidence and balanced debit/credit totals.

The one-day grace intervals allow job completion and evidence ingestion. They do not weaken the required weekly/daily schedules; production alerting must fire before the grace limit is reached.

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

The product profile may become `registered-candidate` when its policy and enforcement are approved. Every production installation must separately pass `dkmodul/deployment-attestation.schema.json`; a successful deployment-specific restore test is mandatory. Hosting-provider names and regions belong in the attestation/evidence registry, not in source code.
