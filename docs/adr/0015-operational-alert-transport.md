# ADR-0015 — Operational alert transport

Status: Accepted

## Context

Production control failures such as failed restore tests must reach an operational channel. The bookkeeping application must not silently treat a failed delivery as a successful alert.

## Decision

The application emits structured alerts through `DkAlertTransport`.

Alerts contain a schema version, control identifier, severity, human-readable message, actor identifier, UTC creation time, and structured details. Only `critical`, `error`, and `warning` severities are accepted.

The transport returns a delivery identifier only after accepting the alert. Transport implementations must throw on delivery failure. The application therefore has a fail-closed delivery boundary without coupling the certified code to email, webhook, paging, or a particular monitoring vendor.

The transport is deliberately an interface: deployment-specific delivery, credentials, TLS policy, retry strategy, and retention remain operational configuration and integration responsibilities.

## Consequence

A production deployment can provide a real alert adapter while the compliance logic remains vendor-neutral. Tests can verify that malformed alerts are rejected before any external delivery is attempted.
