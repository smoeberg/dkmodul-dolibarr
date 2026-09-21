# P0 readiness checklist

## Product identity

- [x] Machine-readable product manifest exists.
- [x] Dolibarr, PHP, MariaDB, DK module, SAF-T and e-invoice target versions are explicit.
- [x] Customer-selectable hosting policy and deployment-attestation schema exist.
- [x] Registered-profile runtime blocks missing or invalid attestations for the actual hosting/backup parties and regions.
- [ ] Every production deployment has an approved, current attestation and supporting evidence.
- [ ] Own Nemhandel/Peppol access-point certification identifier and lifecycle are recorded.
- [ ] Release is changed from `p0-draft` to `registered-candidate` only after all gates pass.

## Compliance lock

- [x] Registered profile fails closed if compliance mode is disabled.
- [x] Normal module removal is blocked in the registered profile.
- [x] Draft manifest can be used in development but cannot be asserted registrable.
- [x] Registered candidate fails closed when the configured external attestation is missing or invalid.
- [ ] Production monitoring alerts on module/profile/configuration drift.
- [ ] Controlled migration and decommissioning procedure is approved.

## Backup, retention and restore

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
