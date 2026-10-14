# ADR-0028 — Production evidence manifest at release boundary

## Status

Accepted.

## Decision

A `registered-candidate` release must load a deployment-scoped production evidence manifest from the configured `DKMODUL_P0_PRODUCTION_EVIDENCE_MANIFEST_PATH` and validate it before the release gate can sign or accept the P0 report.

The manifest must be complete, deployment-bound and structurally valid. An `open` control is therefore a release-blocking condition. The application does not create or fabricate production evidence; it only validates the externally supplied evidence index and its SHA-256 fingerprints.

## Evidence boundary

The manifest is an index of operational evidence. The referenced artifacts remain external production records and must themselves be retained and made available for audit.

## Consequence

A complete application test suite or a signed release report cannot bypass missing production evidence. Candidate signing is blocked until the deployment-scoped manifest is complete.
