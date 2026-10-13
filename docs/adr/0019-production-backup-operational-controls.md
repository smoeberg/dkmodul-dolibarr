# ADR-0019 — Production backup operational controls

Status: Accepted

## Context

The backup evidence boundary already proves individual backup and restore
receipts, but configuration alone must not silently satisfy the production
schedule and retention controls. A registered deployment requires an explicit
weekly full-backup schedule, a daily incremental schedule, and retention that
covers at least five years from the compliance check horizon.

## Decision

DkBackupOperationalPolicy validates the backup configuration resolved from
the signed deployment attestation.

It fails closed when:

- the full schedule does not explicitly identify a weekly cadence;
- the incremental schedule does not explicitly identify a daily cadence;
- the required retention date is less than five years from the compliance
  check date; or
- provider identity or backup region configuration is absent.

The policy does not hardcode a provider, destination, region or schedule.
Those values remain deployment-specific attested configuration.

The existing backup evidence monitor continues to require recent successful
full and incremental evidence, EU/EEA placement, an independent provider,
signed receipt evidence, and retention/immutability through the required
retention date. The policy adds the missing configuration gate before those
runtime claims are accepted.

## Consequence

A deployment cannot become operationally compliant merely by having backup
evidence rows: its signed configuration must also explicitly declare the
required cadence and five-year retention horizon.

This ADR does not claim that a production scheduler is actually running,
that EU/EEA copies exist, that customer-exit/insolvency access has been
tested, or that a quarterly restore has been completed. Those remain
deployment evidence controls and therefore remain open until independently
verified.
