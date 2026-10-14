# ADR-0025: Controlled migration and decommissioning evidence

## Status

Accepted as an application-side evidence contract.

## Decision

Production migration and decommissioning must be represented by ordered,
fail-closed evidence:

1. export is completed and fingerprinted;
2. the export is independently verified;
3. cutover occurs only after verification;
4. decommissioning is scheduled after cutover and cannot be represented as
   already due by the evidence contract.

The application validates source/destination identity, SHA-256 export
fingerprint, actor approval and UTC event ordering. It does not execute
destructive decommissioning.

## Rationale

A migration must not silently turn into data loss. Keeping the destructive
operation outside the application boundary allows the hosting/operator
procedure to require an independent approval and preserve the export evidence
before the source is retired.

## Evidence boundary

Application-side tests prove the validation contract and negative cases.
Production readiness still requires an approved migration/decommissioning
procedure, responsible roles, retention period, customer export handover,
rollback decision, and operational sign-off. Those controls remain open in
the P0 checklist until production evidence exists.
