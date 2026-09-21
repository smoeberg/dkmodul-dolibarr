# Deployment-attestation signing profile

Status: P0 implementation profile. Production key ceremony and custody remain a release gate.

## Envelope

`dkmodul/deployment-attestation.schema.json` describes the signed envelope. `signed_payload` is the base64 encoding of the exact UTF-8 JSON bytes reviewed by the approval authority. The signature is RSA PKCS#1 v1.5 with SHA-256 over those exact decoded bytes. Reformatting the payload after signing invalidates the signature.

The decoded payload contains the deployment, hosting, independent backup, evidence and approval-period fields validated by `DkDeploymentAttestation`.

## Trust boundary

The public keys are supplied through `DKMODUL_ATTESTATION_TRUST_STORE_PATH`. The registered product manifest pins the SHA-256 digest of the entire approved trust-store file. Merely replacing the configured trust store therefore cannot introduce a new trusted signer.

Only keys with matching `key_id`, `RSA-SHA256` algorithm and `active` status are accepted. Revoked or retired keys fail closed.

## Signing procedure

1. Validate the unsigned payload and its referenced evidence under dual control.
2. Set `approval.approved_by`, `approval.approved_at` and `approval.valid_until`.
3. Serialize the final payload once as UTF-8 JSON and preserve those exact bytes.
4. Sign the payload in the approved HSM or signing service using RSA-SHA256.
5. Build the envelope with base64 payload and signature, signer `key_id` and algorithm.
6. Verify the envelope using the release trust store before delivery.
7. Record payload SHA-256, envelope SHA-256, deployment ID, signer, reviewer and delivery event in the approval register.

## Rotation and revocation

A rotation adds the new public key as `active`, changes the pinned trust-store SHA-256 in a reviewed product release and only then begins signing with the new key. The old key becomes `retired` after all still-valid attestations have been renewed.

Emergency revocation marks the key `revoked`, publishes a newly pinned trust store, invalidates affected attestations and requires re-attestation before the registered profile can resume.

The key under `tests/fixtures` is generated solely for automated tests. Its corresponding private key is not stored in the repository and it must never be used for production attestations.
