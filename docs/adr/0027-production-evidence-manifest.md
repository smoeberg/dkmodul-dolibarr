# ADR-0027: Production evidence manifest

## Status

Accepted as an application-side evidence-index contract.

## Decision

Operational controls that cannot be proven by application tests are tracked in
a deployment-scoped production evidence manifest. An item is only evidenced
when it has a stable reference, SHA-256 fingerprint, approver and approval
timestamp.

The manifest is fail-closed: an incomplete manifest cannot be asserted
complete.

## Evidence boundary

This contract does not create or simulate production evidence. It deliberately
keeps open the controls that require real deployment artifacts, approvals,
provider records, restore tests, access-point certification records and
security/privacy documentation.
