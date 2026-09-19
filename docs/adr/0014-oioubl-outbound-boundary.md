# ADR-0014 — Outbound OIOUBL invoice boundary

Status: Accepted

## Context

OIOUBL XML must not be generated directly from Dolibarr SQL. Source data,
document semantics, format mapping, validation, transport and retention change
independently and need separate evidence.

## Decision

The first outbound slice uses this boundary:

1. `DkDolibarrOutboundInvoiceProvider` reads a validated customer invoice.
2. It creates immutable `DkCanonicalInvoice`, party and line objects.
3. `DkOioUblInvoiceGenerator` maps only canonical objects to OIOUBL 2.02 XML.
4. `DkOioUblValidator` applies the official UBL 2.1 XSD and compiled OIOUBL
   Invoice Schematron through Saxon-HE, because the official stylesheet uses
   XSLT 2.0 features that PHP/libxslt does not support. Saxon runs outside the
   hardened PHP process; PHP parses its XML report and rejects every `Error`.
5. The exact validated XML bytes are stored through the immutable document
   archive and linked to the corresponding bookkeeping movement.

Official validation artefacts are pinned to Erhvervsstyrelsen's
`openebusiness/common` commit
`223694e79eb4dbf0895640b35484ab55abae2c42`.

The first slice supports ordinary customer invoices with one VAT rate and bank
transfer payment. Multiple VAT rates, allowances, prepayments, foreign currency,
credit notes and other invoice profiles require explicit fixtures before being
accepted.

## Out of scope

Generation and validation do not constitute sending. NemHandel/Peppol endpoint
lookup, transport, retries, receipts and response messages remain separate
capabilities. `DK-EINV-001` therefore remains `PARTIAL`.
