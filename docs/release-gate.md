# Release gate

The release gate is intentionally fail-closed.

A release cannot be presented as registered-candidate merely because application
tests pass. The P0 readiness checklist must have no unchecked controls, all required
evidence artifacts must exist, and the release-gate workflow must pass on the exact
release commit.

## Automated controls

tests/release/assert-release-gate.php:

1. requires the P0 readiness checklist;
2. parses every unchecked checklist item;
3. fails if any P0 item remains unchecked;
4. requires the core operational evidence artifacts for backup/restore, alert
   transport, and signing/custody;
5. exits non-zero on any missing or unreadable requirement.

.github/workflows/release-gate.yml runs the PHP syntax/unit suite and the
deterministic gate for manual release-gate runs and v* tags.

## Evidence rule

The release gate does not convert deployment assumptions into code evidence.
Installation-specific controls such as backup provider, EU/EØS location,
retention, restore sign-off, attestation, access-point certification and key
ceremony remain open until their real production evidence is supplied.

A passing application test suite is therefore necessary but not sufficient for
registration readiness.
