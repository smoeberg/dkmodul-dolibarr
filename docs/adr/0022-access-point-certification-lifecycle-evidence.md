# ADR-0022 — Access-point certification lifecycle evidence

## Status
Accepted.

## Decision
A registered deployment must carry explicit evidence for the configured NemHandel/Peppol
access-point certification lifecycle. The evidence records the certification identifier,
certificate fingerprint, certified status, validity interval, evidence reference,
reviewer and review date.

Runtime compliance compares the attested values with the trusted product manifest and
fails closed on missing, expired, malformed or mismatching certification evidence.

The application does not claim that a certificate is valid merely because an identifier
is configured. The certification source, renewal/revocation process and authority evidence
remain operational controls and must be retained with the deployment evidence.

## Consequence
A deployment cannot silently replace the certified access point, certificate or validity
period without updating the approved manifest and deployment evidence.
