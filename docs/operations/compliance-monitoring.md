# Compliance monitoring and alerting

Status: P0 product-side implementation. Production recipients and provider-specific delivery adapters remain deployment gates.

## Schedule and checks

Dolibarr registers `DkComplianceMonitorJob` as an hourly cron method. The job is dormant unless `DKMODUL_REGISTERED_PROFILE` is enabled. For every registered deployment it records:

- configuration drift, including compliance mode and required deployment constants,
- signed deployment-attestation validity and pinned trust store,
- full/incremental backup freshness, EU/EØS location, independent provider and retention,
- quarterly restore freshness, hashes, review evidence and bookkeeping balance.

Each execution is appended to `llx_dk_compliance_check` with reason codes, evidence SHA-256, check time and next due time. Updates and deletes are rejected by database triggers.

## Alert lifecycle

An alert fingerprint is stable for one deployment and check type. The first failed/error state appends an `opened` event. Repeated failures remain visible as check results but do not create duplicate alert events. The first subsequent successful check appends `recovered`.

Alert events and delivery attempts are separately append-only. This preserves the distinction between detecting a compliance failure and successfully notifying an operator.

## Delivery boundary

`DkAlertTransportInterface` is the provider-neutral boundary. The bundled outbox transport records a queued delivery attempt without making an external network request. A production adapter may deliver to an authenticated webhook or mail gateway and must return channel, delivery status and an external reference.

Production adapters must use TLS, credentials outside source code, bounded timeouts, retry with backoff, idempotency by `alert_uuid`, recipient allowlisting and payload minimisation. Delivery failures must be retried by the outbox consumer and escalated through a separately monitored channel.

## Required deployment configuration

- `DKMODUL_DEPLOYMENT_ID`
- `DKMODUL_REQUIRED_RETAIN_UNTIL`
- `DKMODUL_HOSTING_REGISTRATION`
- `DKMODUL_DEPLOYMENT_ATTESTATION_PATH`
- `DKMODUL_ATTESTATION_TRUST_STORE_PATH`

Missing values fail closed and create a configuration alert.
