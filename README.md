# Dolibarr DK

Projekt for en dansk, registrerbar Dolibarr-distribution med de funktioner, kontroller og driftskrav der er nødvendige for at kunne anmeldes som digitalt standardbogføringssystem i Danmark.

Projektet er under etablering. Arkitektur, compliance-matrix og produktgrænse udvikles først; implementering følger derefter.

## Mål

At etablere en sporbar kæde fra myndighedskrav til:

1. arkitekturbeslutning,
2. implementation,
3. automatiseret compliance-test,
4. dokumentation til anmeldelse og kontrol.

## Centrale kilder

- Erhvervsstyrelsen: https://erhvervsstyrelsen.dk/
- Retsinformation, BEK nr. 97 af 26/01/2023: https://www.retsinformation.dk/eli/lta/2023/97
- Retsinformation, BEK nr. 98 af 26/01/2023: https://www.retsinformation.dk/eli/lta/2023/98
- Erhvervsstyrelsens standardfilformater: https://git.erst.dk/standard-filformater/standard-filformater

> Projektets compliance-dokumentation er teknisk projektdokumentation og udgør ikke juridisk rådgivning.

## P0 referenceprofil

- `dkmodul/product-manifest.json` er den maskinlæsbare versions- og driftsgrænse og følger med moduldistributionen.
- `docs/p0-readiness-checklist.md` viser åbne gates før `registered-candidate`.
- `docs/operations/backup-retention-restore.md` definerer backup-, retention- og restorebeviser.
- `docs/security/risk-and-control-baseline.md` definerer sikkerheds- og risikobaseline.

En registreret profil fejler lukket, hvis compliance-mode deaktiveres eller manifestet stadig indeholder uafklarede registreringsværdier. Normal modulafinstallation er blokeret i den registrerede profil.
