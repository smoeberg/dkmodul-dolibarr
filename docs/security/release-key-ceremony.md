# Production release key ceremony, custody, rotation and revocation

Status: DRAFT for approval (P0 release gate). Written against the requirements in
`docs/p0-readiness-checklist.md` (item: "Production signing key ceremony, custody,
rotation and revocation procedure is approved") and the trust boundary described in
`docs/security/attestation-signing.md`.

## 1. Purpose and scope

This procedure governs the lifecycle of **two distinct key tracks**:

1. **P0 release signing key** — signs P0 release reports
   (`DkP0ReleaseReportSigner`). At verification time, `DkP0ReleaseGateCollector`
   approves the signer by matching `release_signing.key_id` and
   `release_signing.public_key_sha256` **directly in the product manifest**.
   Revocation/rotation of this key therefore means pinning a new key in a
   reviewed product release; there is no runtime trust store for it.
2. **Attestation/security-risk keys** — verify deployment attestations
   (`DkDeploymentAttestation`) and security/risk evidence. These are verified
   against the attestation trust store (`DKMODUL_ATTESTATION_TRUST_STORE_PATH`)
   whose SHA-256 digest is pinned in the product manifest, which supersedes any
   configuration change.

It applies to production keys only. Test keys under `tests/fixtures` are explicitly
out of scope and must never sign production artefacts.

## 2. Roles

| Role | Responsibility | Minimum count |
|---|---|---|
| Key owner | Accountable for the key set; approves generation, rotation, revocation | 1 (named) |
| Key custodian | Physically/logically holds a key share; participates in ceremonies | 2 (quorum) |
| Reviewer | Validates payload/evidence under dual control before signing | 1 (must not be a custodian signing the same event alone) |
| Witness | Observes ceremony, records log, may be the Key owner | 1 |

Dual control: at least **two custodians (quorum 2 of 2 or 2 of 3)** must be present
for key generation, unsealing and destruction. A single person must never be able
to produce a production signature unobserved.

## 3. Key generation (ceremony)

1. Schedule the ceremony with 5 business days notice; record date, participants and
   location (or secure video link) in the ceremony log.
2. Generate the key pair inside the approved HSM or signing service. The private key
   **never exists outside the HSM**; export is not permitted.
3. Record: `key_id` (monotonic, e.g. `rel-2026-001`), algorithm (RSA-SHA256),
   public key fingerprint (SHA-256), HSM/serial, participants, date.
4. For the attestation track: produce the new trust store (public keys only,
   `active` status) and verify the manifest-pinned digest of the file. For the
   release track: record the key's `key_id` and SHA-256 fingerprint so they can
   be pinned in the product manifest's `release_signing` block in a reviewed
   release.
5. Sign the ceremony log by all participants; commit the log to this repository
   (`docs/security/ceremony-log/`) in a reviewed pull request.

## 4. Custody

- Custody is expressed as **quorum access to the HSM/signing service**, not possession
  of key material (the private key is non-exportable).
- Access is least-privilege, reviewed quarterly, and all HSM operations are logged.
- No private key backup is performed. Loss of the production signing key is handled
  by emergency rotation (§6), not restoration. Losing the key therefore does not
  compromise past artefacts; it requires the ceremony to be repeated to continue
  signing.
- Trust-state files (trust store for the attestation track, `release_signing`
  block for the release track) are versioned in the product manifest; only a
  reviewed product release changes either pin.

## 5. Planned rotation

- Interval: **every 12 months** or on role change of any custodian, whichever first.
- **Release signing key:** the new key's `key_id` and
  `public_key_sha256` are pinned in the product manifest's `release_signing`
  block in a reviewed product release; only then does signing switch to the new
  key (`key_id` appears in the release-report envelope). The old key is removed
  from the manifest pin and marked `retired` in the ceremony log once no still-valid
  report is verified against it.
- **Attestation/security-risk keys:** the new public key is added as `active` in
  the trust store; the pinned trust-store digest changes in a reviewed product
  release; only then does verification accept the new key. The old key becomes
  `retired` once no still-valid attestation is verified against it, and is marked
  `revoked` in the trust store afterwards.

## 6. Emergency revocation

Triggered by: suspected key compromise, departure of a custodian with signing
authority, or verification failure in production.

1. **Attestation/security-risk keys:** mark the key `revoked` in the trust store
   and publish a newly pinned trust store **immediately**; verification is
   fail-closed, so artefacts verified against the revoked key are rejected at once.
2. **P0 release signing key:** remove the old `key_id`/`public_key_sha256` from
   the product manifest's `release_signing` block (or pin the replacement key) in
   an emergency reviewed release. Because the collector matches the manifest pin
   exactly, any report signed by the revoked key_id fails closed as soon as the
   new manifest is in effect. Releases may not be signed until the ceremony in §3
   is completed for a new key.
3. Affected attestations are invalidated; re-attestation is required before the
   registered profile resumes.
4. The incident is recorded in the ceremony log and the compliance incident register.

## 7. Registers and evidence

- Ceremony log: `docs/security/ceremony-log/YYYY-MM-DD-<key-id>.md` (signed by
  participants).
- Trust store: path from `DKMODUL_ATTESTATION_TRUST_STORE_PATH`; digest pinned in
  the product manifest.
- Approval register: payload/envelope SHA-256, deployment ID, signer, reviewer and
  delivery event per signed artefact (see `attestation-signing.md` §Signing procedure
  step 7).

## 8. Approval

This procedure is approved when the checklist item is ticked together with the first
real ceremony log entry. Approver: ________  Date: ________
