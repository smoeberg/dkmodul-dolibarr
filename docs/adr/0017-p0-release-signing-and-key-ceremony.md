# ADR 0017 — P0 release signing and key ceremony

## Status

Accepted.

## Decision

A production P0 release report must be signed by an approved release key before it can be presented as a release artifact.

The application provides the signing and verification boundary through `DkP0ReleaseReportSigner` and obtains the configured signing key through `DkP0ReleaseSigningKeyProvider`. Production private keys are not committed to the repository, product manifest, database, or module source.

Production key custody is an operational control. The approved release process must record:

- key identifier and approved owner;
- independent approval of the key before first use;
- generation/ceremony evidence and custody location;
- public-key fingerprint;
- rotation trigger, overlap policy and effective dates;
- revocation trigger and emergency replacement procedure;
- access review and evidence retention;
- verification that the deployed release signer uses the approved key identifier.

A release pipeline must fail closed when the configured key is missing, invalid, unapproved, expired or revoked. A valid cryptographic signature alone is not evidence that the key is approved.

## Evidence boundary

Automated tests prove canonical signing, verification, tamper detection and rejection of invalid keys. They do not prove production key custody or approval. Those controls require signed operational evidence and remain open in the P0 readiness checklist until independently completed.

## Consequence

The repository can enforce the cryptographic contract without embedding production secrets or pretending that test keys satisfy the production approval requirement.
