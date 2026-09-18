# ADR-0002 — Hosting Model

Status: Accepted

## Decision

The first certifiable Dolibarr DK release is a **managed cloud product**. Self-hosted installations are not inside the initial registered product boundary.

The implementation remains provider-neutral, but every production installation must conform to one documented reference profile.

## Reference profile

- containerised Dolibarr application runtime,
- managed MariaDB/MySQL-compatible database,
- durable document/object storage,
- encrypted transport and encrypted storage,
- centralised application/security logs,
- automated daily incremental backup,
- automated weekly full backup,
- at least one full and incremental backup copy stored on a server in the EU/EEA,
- documented and regularly tested restore procedure,
- separate production and administrative access paths,
- least-privilege administrative access with MFA at infrastructure level,
- monitored backup, storage and runtime health,
- documented suppliers/sub-processors,
- documented security risk assessment and change process.

## Regulatory basis

For registered digital standard bookkeeping systems, BEK 97/2023 §7 requires at least weekly full backup and daily incremental backup, and at least one full and incremental copy on a server in the EU/EEA. §8 requires a high level of IT security for cloud-based systems and a maintained risk assessment.

## Consequences

- "random self-hosting" cannot be marketed as the initially registered Dolibarr DK product.
- infrastructure status becomes part of the compliance dashboard.
- cloud/provider-specific adapters must sit behind runtime interfaces.
- a concrete cloud supplier can be selected later without changing accounting-domain code.
- supplier agreements, DPA and backup locations must be completed before registration.

## Open implementation decision

A concrete infrastructure supplier has not yet been selected. Supplier selection is an operational procurement decision constrained by this ADR rather than an application architecture decision.
