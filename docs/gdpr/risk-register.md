# GDPR risk register — P0 baseline

| Risk | Impact | Current control | Residual action | Status |
|---|---|---|---|---|
| Excess personal data in audit/log payloads | High | Audit ledger stores structured evidence | Review every compliance write path for minimisation | Open |
| Invoice data sent to external processor without approved terms | High | P0 provider isolated behind AP adapter | Complete Inexchange DPA/processor review before live traffic | Open |
| Retention conflicts with erasure request | High | No generic deletion of statutory evidence | Approve per-data-class retention and rights procedure | Open |
| Operational logs retained too long | Medium | No business payload should be logged | Define deployment log retention/cleanup | Open |
| Backup contains personal data without documented processor/region controls | High | Deployment attestation and backup evidence | Select provider and complete DPA/region assessment | Open |
| Data subject request lacks controlled workflow | Medium | No silent evidence mutation | Document intake, assessment, response and processor notification | Open |
| Cross-border/subprocessor processing not assessed | High | Provider-specific boundary documented | Obtain current processor/subprocessor and transfer information | Open |

Risk status must not be interpreted as a legal conclusion. Open items require
deployment-owner approval or documented remediation before the relevant
processing is enabled.
