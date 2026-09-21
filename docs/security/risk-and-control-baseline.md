# IT security risk and control baseline

Status: P0 draft — customer hosting is configurable; each deployment requires its own attestation and evidence review.

## Scope

The assessment covers Dolibarr, the DK module, MariaDB, document/object storage, backup, CI/CD, administrators, support access, the own Nemhandel/Peppol access point and all subprocessors.

## Mandatory control domains

| Domain | P0 deliverable | Minimum evidence |
|---|---|---|
| Network security | Segmentation, private database/storage, controlled ingress/egress, WAF/rate limits | Architecture diagram, firewall policy export and reviewed change log |
| Identity and access | SSO/MFA, least privilege, separate admin roles, joiner/mover/leaver and break-glass | Role matrix, quarterly access review and break-glass test |
| Supplier management | Provider and subprocessor inventory, data locations and security obligations | Contracts, DPA, assurance reports and annual review |
| Logging | Central, time-synchronised, tamper-resistant security and compliance logs | Log-source inventory, retention policy, alert tests and review records |
| Backup/recovery | Controls in `docs/operations/backup-retention-restore.md` | Successful quarterly restore evidence |
| Incident response | Severity model, 24/7 contacts, containment, evidence and notification decisions | Runbook and annual tabletop exercise |
| Data protection | Classification, minimisation, encryption, key management and deletion holds | Article 28 DPA, data-flow map and key/deletion tests |
| Secure delivery | Reviewed changes, protected branch, dependency/SAST/secret scanning and signed releases | CI evidence, SBOM, release signature and vulnerability SLA |
| Access point | Certificate lifecycle, AS4 security, endpoint isolation, message evidence and MLR-Network | Certification plan, key ceremony, conformance tests and operations runbook |

## Initial risk register

| Risk | Impact | Required treatment before candidate |
|---|---|---|
| Unattested customer-selected hosting | Cannot prove backup location, supplier controls or authority access | Block production compliance status until the deployment attestation is approved |
| Own access point not certified | E-invoices cannot be relied on as production-delivered | Complete Nemhandel/Peppol certification, certificate and conformance lifecycle |
| Compliance mode disabled | Required controls may be bypassed | Registered-profile fail-closed lock and monitoring |
| Module removal drops DB guards | Immutability may disappear | Block normal removal in registered profile; define controlled migration procedure |
| Lost encryption keys | Five-year records become unreadable | Dual-control key recovery and periodic recovery test |
| Backup exists but is not restorable | Statutory records may be unavailable | Quarterly end-to-end restore and reconciliation |

The named risk owner, likelihood, impact score, treatment deadline and residual risk acceptance must be recorded for each deployment. A provider is configuration/evidence, not a compiled product dependency.
