# NemHandel / Peppol access point — requirements, decision and plan

Status: DRAFT (P0 blocker). Companion to `docs/compliance/` matrix rows
DK-EINV-001/002 and `docs/architecture` Peppol BIS notes. Written 2026-10-07.

## 0. Decision first: own access point or third-party AP?

Erhvervsstyrelsen supports access points operated by the bookkeeping-system
provider and integration with existing access points. The first P0 decision is
whether dkmodul will operate its own AP or use a third-party AP.

1. **Own access point** — the vendor operates the AP and must document
   adherence to the principles of a recognised information-security standard
   (e.g. ISO/IEC 27001) and the applicable Peppol access-point specifications.
   **No formal ISO certification is required by the NemHandel AP approval
   requirement; the relevant security framework must be documented and
   demonstrably implemented.**
2. **Third-party access point** — integrate with an existing Danish/international
   AP provider. The provider relationship, technical setup and contractual
   responsibilities must be documented; the required auditor/revisor approval
   of the AP and setup must be obtained where applicable.

The AP decision is separate from the choice of SMP hosting. An own AP can use
a hosted SMP, and a third-party AP may expose or operate SMP services on behalf
of its customers.

### NemHandel, Peppol and the product boundary

NemHandel and Peppol are related but distinct infrastructures and must not be
treated as one certification or transport requirement:

- **NemHandel/eDelivery:** Danish infrastructure for domestic traffic and the
  relevant Danish participant/registration model.
- **Peppol:** international network/infrastructure for cross-border and other
  Peppol traffic, using applicable Peppol specifications and PKI.
- **Common AP platform:** an AP implementation may support both infrastructures
  where the chosen architecture and registrations permit it.
- **SMP / metadata:** participant and service metadata is a separate operational
  concern from AP transport.
- **Document layer:** OIOUBL 2.02 and Peppol BIS Billing 3 are document-format/
  process concerns and must be scoped independently from transport.

The legal entity, CVR and required business certificates/credentials must be
identified for the selected NemHandel participation model. Do not assume that
the AP transport certificate itself is a MitID Erhverv certificate; the
certificate/credential role must be verified for the concrete production
architecture and current NemHandel requirements.

**P0 assumption (to be confirmed):** the vendor operates its own access point,
implemented on an eDelivery AS4-conformant stack (Erhvervsstyrelsen recommends
Oxalis, but does not mandate it). If this assumption changes, the own-AP
workstream becomes a third-party provider selection and integration track.

## 1. Organisational prerequisites (start immediately — longest lead time)

- [ ] Decide and document model (own AP vs third-party AP) as an ADR.
- [ ] If own AP: establish ISMS documentation covering the principles of
      ISO/IEC 27001 relevant to the AP service (access control, change
      management, incident response, logging, encryption in transit/at rest).
      Map each control to existing evidence in this repo (compliance monitor,
      audit ledger, backup/restore evidence) where it exists.
- [ ] If third-party AP: shortlist providers; obtain the required auditor/
      revisor approval process; document the technical interface (AS4 endpoint,
      capabilities, SLA), DPA and exit requirements.
- [ ] Ensure the Danish legal entity, CVR and required NemHandel credentials/
      certificates are identified for the selected participation model.
- [ ] Contact NemHandel/Erhvervsstyrelsen and request the current production
      approval/registration material before committing the final architecture.

## 2. Technical prerequisites (code-side)

### 2.1 NemHandel / eDelivery

- [ ] Deploy and configure a conformant eDelivery AS4 stack (e.g. Oxalis) or
      equivalent.
- [ ] Establish the required Danish production credentials/certificates and
      lifecycle process for the selected AP model.
- [ ] Implement the relevant participant/metadata registration and lookup
      flow for NemHandel.
- [ ] Validate the AP against the current NemHandel test/demo environment.

### 2.2 Peppol

- [ ] Obtain the applicable Peppol production certificate(s) through the
      relevant Peppol Authority route.
- [ ] Implement or integrate the required Peppol SMP/metadata publication and
      participant registration model.
- [ ] Run the OpenPeppol conformance tests applicable to the selected AP and
      archive the results.

### 2.3 Shared transport and document layer

- [ ] Decide separately between self-hosted and hosted SMP.
- [ ] Outbound: OIOUBL 2.02 / Peppol BIS Billing 3 generation exists in the
      product; wire it to the transport layer with per-message logging and
      retry semantics; failure handling must be fail-closed and audited.
- [ ] Inbound: receive, validate and route incoming documents to the right
      customer ledger; duplicate detection; asynchronous acknowledgement.
- [ ] Validate the complete test path before production traffic.

## 3. Approval and evidence

### Own AP

- [ ] Submit the required security/ISMS documentation and evidence of
      adherence to the applicable Peppol AP specifications to the competent
      NemHandel/Erhvervsstyrelsen process.
- [ ] Obtain and archive the resulting AP approval/registration evidence.

### Third-party AP

- [ ] Select provider and obtain the required auditor/revisor approval of the
      provider and technical setup.
- [ ] Sign DPA/SLA and document service ownership, incident handling, certificate
      responsibility and exit/portability.

### Common evidence and registration

- [ ] Register the product/system in the applicable Danish bookkeeping-system
      and NemHandel registers.
- [ ] Record AP identity, participant identifiers, certificate fingerprints,
      validity periods and rotation/revocation procedures.
- [ ] Keep AP certificate lifecycle evidence separate from the P0 release-signing
      key lifecycle in `docs/security/release-key-ceremony.md` §7. A Peppol/
      NemHandel certificate must not be treated as the product release-signing
      key.
- [ ] Evidence bundle: approvals, conformance test results, certificates,
      registration/metadata snapshots and operational evidence, filed so the
      compliance monitor/P0 release process can consume the relevant evidence.

## 4. Timeline (indicative internal planning estimate)

The following is an internal planning estimate, not an external SLA:

| Step | Lead time | Notes |
|---|---|---|
| ISMS documentation | 4–8 weeks | reuses repo evidence where possible |
| AS4 stack in test | 2–4 weeks | parallel with ISMS |
| Peppol PKI + SMP | 2–6 weeks | authority/provider dependent |
| Conformance tests | 1–2 weeks | after test environment is live |
| External approval/registration | 4–12 weeks | external and not guaranteed |
| Production traffic | after approval/registration | start with limited pilot customers |

Critical path is the external approval/registration where applicable;
technical work and evidence should be completed before submission where the
process requires it.

## 5. P0 document-scope matrix

The P0 scope must be explicit before implementation is frozen:

| Document/process | P0 | Notes |
|---|---|---|
| Invoice | [ ] | Required for the initial e-invoicing flow |
| Credit note | [ ] | Decide explicitly |
| Invoice response | [ ] | Decide explicitly |
| Order | [ ] | Decide explicitly |
| Order response | [ ] | Decide explicitly |
| Other OIOUBL/Peppol documents | [ ] | Defer unless required by P0 |

## 6. Open questions

- [ ] Own AP vs third-party AP — decision meeting needed.
- [ ] Which Peppol Authority/provider route applies for the DK production
      certificate and SMP model.
- [ ] Self-hosted vs hosted SMP.
- [ ] Exact NemHandel credential/certificate roles for the selected AP model.
- [ ] P0 document scope from §5.
- [ ] Third-party AP exit/portability requirements if that model is selected.
