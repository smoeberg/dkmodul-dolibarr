# ADR-0017 — Inbound supplier-invoice draft approval

Status: Accepted

## Decision

Only an inbound OIOUBL row with immutable `validated` evidence may be converted.
The supplier must resolve uniquely by Danish CVR in the same entity. An explicit
actor approval creates a Dolibarr `FactureFournisseur` through its public
`create()` and `addline()` APIs.

The supplier reference, dates, currency, descriptions, quantities, prices and
VAT rates come from the already validated XML. Duplicate conversion and an
existing supplier/reference pair are rejected. Creation, lines, provenance and
AuditLedger event share one database transaction.

The resulting invoice must remain draft. This service never calls validation,
approval, accounting transfer or posting APIs. Those actions require a later
workflow and separate authorization.
