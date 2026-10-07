# Nemhandel / Peppol access point certification — checklist and plan

Status: DRAFT (P0 blocker). Companion to `docs/compliance/` matrix rows
DK-EINV-001/002 and `docs/architecture` Peppol BIS notes. Written 2026-10-07.

## 0. Decision first: own access point or third-party AP?

Erhvervsstyrelsen allows two models for a bookkeeping system:

1. **Own access point** — the product (or the vendor) is approved as an access
   point and registered in Nemhandel by Erhvervsstyrelsen. Requires documented
   adherence to the principles of a recognised ISMS standard (e.g. ISO 27001)
   and the Peppol access point specifications. No formal certification required,
   but the security framework must be documented and demonstrated.
2. **Third-party access point** — integrate with an existing Danish/international
   AP provider. Requires the **revisor (auditor) to approve the chosen AP and the
   technical setup between the system and the AP**.

Note: Nemhandel is for domestic (DK) traffic only and requires a Danish CVR and
MitID Erhverv certificate. Cross-border traffic requires Peppol (OpenPeppol
network). A product like dkmodul typically needs **both**, or a Peppol AP that
also covers Nemhandel via the eDelivery infrastructure.

**P0 assumption (to be confirmed):** the vendor operates its own access point,
implemented on an eDelivery AS4-conformant stack (Erhvervsstyrelsen recommends
Oxalis, but does not mandate it). If this assumption changes, §3 collapses into
"choose vendor + auditor approval" and the timeline shortens considerably.

## 1. Organisational prerequisites (start immediately — longest lead time)

- [ ] Decide and document model (own AP vs third-party AP) as an ADR.
- [ ] If own AP: establish ISMS documentation covering the principles of
      ISO/IEC 27001 relevant to the AP service (access control, change
      management, incident response, logging, encryption in transit/at rest).
      Map each control to existing evidence in this repo (compliance monitor,
      audit ledger, backup/restore evidence) where it exists.
- [ ] If third-party AP: shortlist providers; get auditor sign-off process
      started; document the technical interface (AS4 endpoint, capabilities,
      SLA) and the data-processing agreement.
- [ ] Ensure a Danish CVR, a MitID Erhverv certificate, and the legal entity
      that will sign the Nemhandel agreement are identified.
- [ ] Register the intent with Erhvervsstyrelsen (contact via nemhandel.dk
      support) and request the current approval material — the concrete
      requirements may have been updated since this document was written.

## 2. Technical prerequisites (code-side)

- [ ] eDelivery AS4 transport: deploy and configure a conformant stack
      (e.g. Oxalis) or equivalent; TLS + Peppol certificates (PKI).
- [ ] Obtain Peppol production certificate(s) for the AP
      (SMP/AP certificates via the Peppol Authority channel relevant for DK).
- [ ] SMP: publish service metadata (participant identifiers, document types,
      process identifiers) — decide self-hosted SMP vs hosted SMP.
- [ ] Participant registration flow: dkmodul must be able to register a
      customer's participants in Nemhandel/Peppol (automatic registration with
      customer consent is recommended by Erhvervsstyrelsen; manual web
      registration is acceptable).
- [ ] Outbound: OIOUBL 2.02 / Peppol BIS Billing 3 generation exists in the
      product; wire it to the transport layer with per-message logging and
      retry semantics; failure handling must be fail-closed and audited.
- [ ] Inbound: receive, validate and route incoming documents to the right
      customer ledger; duplicate detection; asynchronous acknowledgement.
- [ ] Conformance: run the OpenPeppol conformance test suite against the AP in
      test; archive results as evidence.
- [ ] Demo/test environment: validate against the Erhvervsstyrelsen external
      demo/test environment for access points before production traffic.

## 3. Approval and evidence

- [ ] Submit ISMS documentation + Peppol specification adherence to
      Erhvervsstyrelsen for approval as access point (if own AP).
- [ ] Or: completed auditor approval of third-party AP + technical setup.
- [ ] Register the product in the provider register and the Nemhandel
      provider registration (bogføringssystem registration, cf. the
      digital-standard-bookkeeping-systems regulation).
- [ ] Record certificate fingerprints, validity periods and rotation plan in
      the same registers used by the key ceremony procedure
      (`docs/security/release-key-ceremony.md` §7) — Nemhandel/Peppol
      certificates must be renewed on time or the AP stops working fail-closed.
- [ ] Evidence bundle: approvals, conformance test results, certificates,
      SMP snapshots — filed under the compliance monitor so the P0 release
      gate collector can consume them.

## 4. Timeline (indicative, own-AP model)

| Step | Lead time | Notes |
|---|---|---|
| ISMS documentation | 4–8 weeks | reuses repo evidence where possible |
| AS4 stack in test | 2–4 weeks | parallel with ISMS |
| Peppol PKI + SMP | 2–6 weeks | authority application |
| Conformance tests | 1–2 weeks | after test environment is live |
| Erstyrelsen approval | 4–12 weeks | external, not controllable |
| Production traffic | after approval | start with limited pilot customers |

Critical path is the external approval; everything technical should be done
and evidenced before submitting.

## 5. Open questions

- [ ] Own AP vs third-party AP — decision meeting needed.
- [ ] Which Peppol Authority applies for the DK production certificate route.
- [ ] OIOUBL vs Peppol BIS: which document types are in the P0 scope (invoice
      only, or invoice + credit note + response messages).
