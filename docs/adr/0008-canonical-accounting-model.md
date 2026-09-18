# ADR-0008 — Canonical Accounting Model

Status: Accepted

## Context

Dolibarr's database model and the Danish SAF-T format evolve independently. Coupling SAF-T XML generation directly to Dolibarr SQL would make every Dolibarr or SAF-T change a cross-cutting rewrite.

## Decision

Dolibarr DK introduces a version-neutral Canonical Accounting Model (CAM).

```text
Dolibarr database
      |
      v
Dolibarr accounting adapter
      |
      v
Canonical Accounting Model
      |
      +--> SAF-T 2.1 mapper/exporter
      +--> Regnskab Basis
      +--> VAT/reporting
      +--> compliance checks
```

The CAM uses business concepts rather than SAF-T XML element names.

Initial core objects:

- company/context,
- accounts,
- parties,
- fiscal periods,
- journals,
- transactions,
- transaction lines,
- document references,
- tax/VAT codes,
- correction relations.

## Monetary values

Amounts are represented as decimal strings, not PHP floats.

Canonical precision is initially 8 decimal places, matching Dolibarr's bookkeeping database precision.

This prevents binary floating-point errors from becoming XML/reporting discrepancies.

## Transaction invariants

A canonical transaction must have:

- stable transaction id,
- journal code,
- transaction date,
- registration timestamp,
- actor/program identity,
- at least two lines,
- balanced debit and credit totals.

## Versioning

SAF-T version details belong only in the SAF-T mapping layer.

The canonical model may add optional fields over time, but a SAF-T 2.1 mapper must explicitly declare which canonical fields it consumes and how missing/optional values are handled.
