# GDPR data protection baseline

## Purpose

This document establishes the minimum GDPR control baseline for the DK-modul
before production registration and operation. It is a control document, not a
legal assessment.

## Processing inventory

| Processing area | Personal data | Purpose | Retention/control | External processor |
|---|---|---|---|---|
| User/accounting actions | Dolibarr user ID, actor identity and audit provenance | Accountability and audit trail | Accounting/audit retention; append-only controls | Hosting party |
| Inbound/outbound e-invoices | Endpoint identifiers, supplier/customer references and document content where present | Invoice exchange and accounting evidence | Document retention policy; immutable archive | Inexchange for P0 AP transport |
| Audit ledger | Actor ID, event metadata and technical provenance | Compliance evidence | Append-only retention aligned with underlying compliance evidence | Hosting party |
| Backup/restore evidence | Deployment metadata and operator/actor references where recorded | Restore and continuity evidence | Backup/restore retention policy | Selected backup provider |
| Security/compliance monitoring | Check status, hashes, timestamps and actor/operation metadata | Detect control failures and prove operation | Operational retention policy; no business document payload | Hosting party |

## Data minimisation rules

- Do not add personal data to audit payloads when a stable internal identifier or
  hash is sufficient.
- Do not send invoice payloads or free-form accounting text to external AI or
  diagnostics services unless a separately approved processing purpose and
  processor agreement covers it.
- Access Point credentials are runtime secrets and must not be stored in source,
  audit events or ordinary configuration output.
- Logs and monitoring evidence must contain identifiers and hashes needed for
  correlation, not copies of invoice XML or other document payloads.
- GDPR-relevant deletion must never be implemented by mutating compliance
  evidence. Where legal erasure is applicable, the affected data boundary and
  retention obligation must be assessed explicitly.

## Retention boundary

The accounting/document retention requirement takes precedence over an
application-level generic deletion mechanism. DK-modul therefore uses
purpose-specific retention controls:

1. accounting and statutory evidence follows the applicable accounting
   retention period;
2. transport and audit evidence follows the retention of the compliance
   activity it proves;
3. operational logs are retained only as long as operational/security purposes
   require;
4. temporary processing artefacts must have an explicit cleanup policy.

A future implementation may add automated cleanup only after each data class has
an approved retention period and an evidence-preservation rule.

## Data subject rights

Requests for access, rectification, restriction, objection or erasure must be
handled through a controlled process that first identifies:

- the data subject and relevant entity;
- the processing purpose and legal basis;
- whether the record is statutory accounting evidence;
- whether another retention obligation overrides deletion;
- what downstream processors must be notified.

The product must not silently delete or rewrite accounting/audit evidence in
response to an ordinary application-level delete operation.

## Processor boundary

For P0, Inexchange is a transport processor/service dependency for outbound and
inbound e-invoice exchange. The production deployment must maintain the
applicable DPA, processing instructions, security terms and subprocessor
information before live personal-data traffic is enabled.

The hosting and backup providers are deployment-specific and must be recorded in
the deployment/processor register rather than hardcoded into the module.

## Open evidence required before production

- signed/approved records of processing activities;
- legal-basis and retention assessment for each processing class;
- DPA/processor terms for Inexchange, hosting and backup providers;
- subprocessor list and transfer assessment where applicable;
- documented data-subject request procedure;
- security incident/breach response procedure;
- approved deletion/cleanup schedule for operational data;
- deployment-specific processor register.

## Control owner

The deployment owner is responsible for approving the legal basis, retention
periods, processors and operational procedures. Code and technical controls
cannot by themselves establish GDPR compliance.
