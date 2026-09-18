# ADR-0001 — Product Boundary

Status: Accepted

## Beslutning

"Dolibarr DK" defineres som en samlet, versionsstyret distribution bestående af Dolibarr, DK-modulet, obligatoriske accounting-funktioner, understøttet runtime/hosting og nødvendige integrationer.

## Begrundelse

Registrerbarhed og compliance afhænger både af applikationsfunktioner og driftsmæssige kontroller. Derfor kan DK-modulet ikke behandles som et isoleret plugin uden produktgrænse.

## Konsekvenser

- Understøttede versioner skal dokumenteres.
- Compliance kan kun loves for godkendte kombinationer.
- Hosting/runtime skal indgå i test- og dokumentationspakken.
