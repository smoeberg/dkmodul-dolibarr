# Production key ceremony evidence

This document is the controlled evidence template for the production signing-key ceremony. It does not contain private keys, seed material, recovery codes or other secret material.

## Ceremony identity

- Ceremony ID:
- Date/time (UTC):
- Environment/deployment ID:
- Product release:
- Procedure version: `docs/security/release-key-ceremony.md`
- Key track: release signing / deployment-attestation / security-risk evidence

## Custodians and witnesses

Record roles, not secret material.

| Role | Name/identifier | Present | Attestation/reference |
| --- | --- | --- | --- |
| Custodian 1 |  |  |  |
| Custodian 2 |  |  |  |
| Witness |  |  |  |

## Key-generation evidence

- Generation mechanism / HSM or approved key store:
- Non-exportability confirmed:
- Algorithm:
- Key size:
- Key identifier:
- Public-key fingerprint (SHA-256):
- Generation log reference:
- Backup/recovery control reference:
- Destruction/cleanup confirmation for temporary material:

## Trust-store / manifest binding

- Manifest key identifier:
- Manifest public-key fingerprint:
- Trust-store path/version:
- Fingerprint verified by:
- Verification timestamp:
- Any mismatch or exception: none / describe

## Functional verification

Record only hashes, identifiers and pass/fail outcomes.

- Signing test: PASS / FAIL
- Signature verification against approved trust store: PASS / FAIL
- Deployment-attestation verification: PASS / FAIL / N/A
- Security-risk evidence verification: PASS / FAIL / N/A
- P0 aggregate release report signing: PASS / FAIL
- Verification log reference:

## Ceremony approval

- Procedure steps completed: YES / NO
- Exceptions approved: YES / NO / N/A
- Evidence package hash:
- Approved by:
- Approval timestamp:
- External evidence/reference ID:

## Evidence handling

The completed record must be stored outside source control in the approved controlled evidence location. Only a non-secret reference, package hash and relevant verification metadata should be copied into the release evidence set. Private keys and recovery material must never be committed to this repository.
