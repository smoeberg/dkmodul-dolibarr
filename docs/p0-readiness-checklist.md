# P0 readiness checklist

## Product identity

- [x] Deterministic fail-closed P0 release report and JSON schema exist.
- [x] Runtime collector derives available release gates from verified evidence and persists the report append-only.
- [ ] Signed security/risk evidence source is connected to the runtime collector.
- [ ] Production release report is digitally signed by an approved release key.
- [x] Machine-readable product manifest exists.
- [x] Dolibarr, PHP, MariaDB, DK module, SAF-T and e-invoice target versions are explicit.
- [x] Customer-selectable hosting policy and deployment-attestation schema exist.
- [x] Registered-profile runtime blocks missing or invalid attestations for the actual hosting/backup parties and regions.
- [ ] Every production deployment has an approved, current attestation and supporting evidence.
- [ ] Own Nemhandel/Peppol access-point certification identifier and lifecycle are recorded.
- [x] Registered-candidate runtime rejects a planned, unidentified or expired access-point certification.
- [ ] Release is changed from `p0-draft` to `registered-candidate` only after all gates pass.

## Compliance lock

- [x] Registered profile fails closed if compliance mode is disabled.
- [x] Normal module removal is blocked in the registered profile.
- [x] Draft manifest can be used in development but cannot be asserted registrable.
- [x] Registered candidate fails closed when the configured external attestation is missing or invalid.
- [x] Runtime verifies attestation signature, trusted key status and approval validity period.
- [ ] Production signing key ceremony, custody, rotation and revocation procedure is approved.
- [x] Hourly product-side monitoring records append-only checks and deduplicated alert lifecycle events.
- [ ] Production webhook/mail adapter, recipients, retry worker and escalation channel are approved and tested.
- [ ] Controlled migration and decommissioning procedure is approved.

## Backup, retention and restore

- [x] Backup and restore evidence is append-only and integration-tested on MariaDB.
- [x] Runtime status fails closed for stale/missing full, incremental and quarterly restore evidence.
- [x] Runtime status checks EU/EØS location, independent provider, signed receipt, retention and balanced restore.
- [ ] Weekly full and daily incremental jobs configured.
- [ ] At least one full and incremental copy proven in EU/EØS for every deployment.
- [ ] Five-year retention and early-delete denial tested.
- [ ] Customer-exit/insolvency access path tested.
- [ ] Quarterly end-to-end restore completed and signed off.

## Security

- [ ] System/data-flow architecture completed.
- [ ] Formal risk register approved.
- [ ] IAM, network, supplier, logging, incident and data-protection controls evidenced.
- [ ] Article 28 processor agreement and subprocessor inventory approved.
- [ ] Own access point certification and certificate operations evidenced.
