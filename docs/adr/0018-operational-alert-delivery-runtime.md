# ADR-0018: Operational alert delivery runtime

- Status: accepted
- Date: 2026-10-13

## Decision

Compliance monitoring alerts are persisted before transport and delivered by a separate worker. The worker is fail-closed:

1. queued/retry deliveries are atomically claimed so concurrent workers cannot deliver the same row concurrently;
2. primary delivery must return a non-empty provider reference;
3. transport failure is persisted with an error fingerprint and exponential retry is scheduled;
4. after the configured retry limit, a separate escalation channel is attempted;
5. escalation failure changes the delivery state to `failed` and the worker exits unsuccessfully when configured to fail closed;
6. stale `processing` rows are returned to the retry queue after 15 minutes;
7. every state transition remains appendable in the delivery evidence table.

The application does not choose a vendor, recipient list, SMTP account, webhook secret or escalation destination. Those are deployment controls and must be approved and evidenced outside the source tree.

## Security and compliance boundary

The primary and escalation implementations must reject unconfirmed delivery. A successful HTTP request without a provider acknowledgement is not sufficient evidence. Credentials and TLS configuration remain outside the repository.

This ADR does not mark the P0 control "production webhook/mail adapter, recipients, retry worker and escalation channel are approved and tested" complete. The worker and testable application boundary are code evidence; production adapter approval and operational evidence remain required.
