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
   Revocation/rotation of this key therefore means changing the approved manifest
   pin in a reviewed product release; there is no runtime trust store for it.
2. **Attestation/security-risk keys** — sign deployment attestations
   (`DkDeploymentAttestation`) and security/risk evidence. These are verified
   against the attestation trust store (`DKMODUL_ATTESTATION_TRUST_STORE_PATH`)
   whose SHA-256 digest is pinned in the product manifest.

These trust boundaries must not be conflated. A key being present in the attestation
trust store does **not** make it an approved P0 release signer, and the P0 release
signer is not approved for attestation/security-risk evidence through that trust store.

It applies to production keys only. Test keys under `tests/fixtures` are explicitly
out of scope and must never sign production artefacts.

## 2. Roles

| Role | Responsibility | Minimum count |
|---|---|---|
| Key owner | Accountable for the key set; approves generation, rotation, revocation | 1 (named) |
| Key custodian | Controls HSM/signing-service access; participates in ceremonies | 2 (quorum) |
| Reviewer | Validates payload/evidence and approved key metadata before signing | 1 (must not be a custodian acting alone) |
| Witness | Observes ceremony and records the ceremony log; may be the Key owner | 1 |

Dual control: at least **two custodians (quorum 2 of 2 or 2 of 3)** must be present
for production key generation, HSM unsealing and destruction. A single person must
never be able to produce a production signature unobserved.

## 3. Key generation (ceremony)

### 3.1 P0 release signing key

1. Schedule the ceremony with 5 business days notice; record date, participants and
   location (or secure video link) in the ceremony log.
2. Generate the RSA key pair inside the approved HSM or signing service. The private
   key **never exists outside the HSM**; export is not permitted.
3. Record: `key_id`, algorithm (`RSA-SHA256`), public-key SHA-256 fingerprint,
   HSM/key reference, participants and date.
4. Verify that the proposed `key_id` and public-key fingerprint are the values
   approved for `release_signing` in the product manifest, or prepare the reviewed
   manifest change that will pin them before the key is activated for production.
5. Sign the ceremony log by all participants and commit the log to this repository
   (`docs/security/ceremony-log/`) in a reviewed pull request.

The release key is **not** approved through the attestation trust store.

### 3.2 Deployment-attestation and security-risk evidence keys

1. Generate the RSA key pair inside the approved HSM or signing service. The private
   key is non-exportable.
2. Record the key ID, algorithm, public-key SHA-256 fingerprint, HSM/key reference,
   participants and date.
3. Add the public key to the attestation trust store with the appropriate lifecycle
   status (normally `active`).
4. Compute and independently verify the trust-store SHA-256 digest.
5. Update the product manifest's pinned trust-store digest only through the reviewed
   release process.
6. Sign and retain the ceremony log as above.

The attestation trust store is used for deployment-attestation and security-risk
evidence verification; it is not the approval mechanism for P0 release reports.

## 4. Custody

- Custody is expressed as quorum access to the HSM/signing service, not possession
  of private key material.
- Access is least-privilege, reviewed quarterly, and all HSM/signing operations are
  logged.
- No private-key backup is performed. Loss of a production key is handled by
  emergency rotation, not restoration.
- Trust stores contain public keys only.
- The release-signing approval is pinned in the product manifest as key ID plus
  public-key SHA-256. The attestation trust boundary is pinned separately by the
  trust-store SHA-256 digest.
- Changes to either production trust boundary require a reviewed product release.

## 5. Planned rotation

### 5.1 P0 release signing key

- Rotate every **12 months** or on role change of any custodian, whichever comes first.
- Generate the replacement key under the same dual-control ceremony.
- Record its key ID and public-key SHA-256 fingerprint.
- Prepare a reviewed product-manifest change that replaces
  `release_signing.key_id` and `release_signing.public_key_sha256` with the new
  approved values.
- Do not switch production signing to the new key until the reviewed release carrying
  the new manifest pin is deployed.
- After the new pin is active, sign only with the new key.
- Retain the old key's ceremony record for audit. Historical verification of an old
  release must use the release's archived metadata and corresponding public key;
  the current release-signing manifest pin is not a historical key archive.

### 5.2 Deployment-attestation and security-risk evidence keys

- Rotate every **12 months** or on role change of any custodian, whichever comes first.
- Add the replacement public key to the trust store as `active`.
- Verify and update the product manifest's pinned trust-store digest through a
  reviewed release.
- Once the replacement is active, the old key may be marked `retired` when no
  still-required artefact needs it for current verification.
- If compromised, use `revoked` status and publish a new pinned trust store as
  described in §6.2.

## 6. Emergency revocation

Triggered by: suspected key compromise, loss of exclusive control, departure of a
custodian with signing authority, or unexplained signing/verification failure.

### 6.1 P0 release signing key

1. **Freeze P0 release signing immediately.** No new production release report may
   be approved while the affected key is under investigation.
2. Generate a replacement key under the dual-control ceremony.
3. Update `release_signing.key_id` and
   `release_signing.public_key_sha256` in a reviewed emergency product release.
4. Deploy the release containing the new manifest pin before resuming production
   signing.
5. Record the incident, affected key fingerprint, decision, participants and
   replacement key in the ceremony/compliance incident records.

The current runtime does not consult the attestation trust store for P0 release
signer revocation. Therefore, revoking a release key in the attestation trust store
does **not** by itself reject P0 release signatures.

Once the replacement manifest pin is deployed, the collector fails closed for any
release signer whose key ID or public-key fingerprint does not match the new pin.
Previously signed release reports are not retroactively declared invalid solely
because the key is rotated or revoked; their validity is assessed under the
applicable release-evidence and incident procedure.

### 6.2 Deployment-attestation and security-risk evidence keys

1. Mark the affected public key `revoked` in the attestation trust store.
2. Generate or select a replacement key and publish a new trust store.
3. Compute the new trust-store SHA-256 digest and update the manifest pin through
   the emergency reviewed release process.
4. Verification remains fail-closed: evidence signed by a key that is not trusted
   by the currently pinned trust store is rejected.
5. Re-attestation is required where the affected deployment evidence can no longer
   be trusted.
6. Record the incident and ceremony evidence.

## 7. Registers and evidence

- Ceremony log:
  `docs/security/ceremony-log/YYYY-MM-DD-<key-id>.md`, signed by participants.
- P0 release key register: key ID, public-key SHA-256, HSM/key reference, ceremony
  date, approval/release containing the manifest pin, rotation/revocation event and
  incident reference where applicable.
- Attestation trust-store register: key ID, public-key SHA-256, lifecycle status,
  trust-store digest, approval/release containing the digest pin and lifecycle event.
- Signing evidence register: payload/envelope SHA-256, deployment ID where relevant,
  signer, reviewer and delivery event per signed artefact (see
  `attestation-signing.md` §Signing procedure step 7).

Private key material must never be committed to this repository.

## 8. Approval

This procedure is approved when the checklist item is ticked together with the first
real production ceremony log entry. Approval must name the responsible approver and
date.

Approver: ________  Date: ________
