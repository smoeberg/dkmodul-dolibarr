# GDPR processing register — P0 baseline

| ID | Activity | Data classes | System boundary | Retention decision | Evidence |
|---|---|---|---|---|---|
| GDPR-001 | Accounting and statutory audit | Actor ID, accounting references, invoice/document data | Dolibarr + dkmodul | Statutory retention assessment required | Accounting/audit records |
| GDPR-002 | Inbound e-invoice processing | Supplier/customer endpoint data, invoice XML | Inexchange → staging → Dolibarr | Statutory retention assessment required | Immutable inbound archive |
| GDPR-003 | Outbound e-invoice processing | Customer endpoint data, invoice XML | Dolibarr → Inexchange | Statutory retention assessment required | Immutable outbound archive + transport events |
| GDPR-004 | Audit/compliance monitoring | Actor IDs, hashes, timestamps, control results | dkmodul audit/monitoring | Align with evidence purpose | Audit ledger + monitoring evidence |
| GDPR-005 | Backup and restore | Data contained in protected backups + deployment metadata | Deployment backup platform | Backup policy assessment required | Backup/restore evidence |
| GDPR-006 | Operational logging | Technical identifiers, errors and control status | Dolibarr/runtime | Minimise and time-limit | Runtime logs |
| GDPR-007 | Support/administration | Admin actor identity and configuration actions | Dolibarr admin/runtime | Operational retention assessment required | Admin/audit evidence |

This register is intentionally a baseline. It must be approved against the actual
deployment, contracts, legal bases and data flows before production use.
