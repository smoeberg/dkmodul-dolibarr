# Product Boundary — Dolibarr DK

## Formål

Dette dokument definerer den produktgrænse, som "Dolibarr DK" udvikles, testes og senere anmeldes ud fra.

## Produktdefinition

Dolibarr DK består af:

- Dolibarr 24.0.x (første certificeringsbaseline),
- Dolibarr Advanced Accounting,
- DK-modulet i dette repository,
- managed-cloud referenceprofil med PHP 8.2 og MariaDB 11.4,
- de eksterne integrationsservices, som er nødvendige for dansk compliance.

Produktet skal kunne identificeres entydigt ved versioner af:

- Dolibarr,
- DK-modulet,
- database/runtime,
- SAF-T schema/mapping,
- e-faktureringsintegration,
- hostingprofil.

## Principper

1. Ingen ændringer i Dolibarr core medmindre et dokumenteret regulatorisk krav ikke kan opfyldes via modul, hooks, triggers eller services.
2. Compliance-kritiske funktioner må ikke kunne deaktiveres i en registreret/understøttet Dolibarr DK-installation.
3. Alle lov-/myndighedskrav skal kunne spores til implementation, test og dokumentation.
4. SAF-T 2.1 er målversionen for den kommende certificerbare release.
5. Drift/hosting er en del af produktarkitekturen og ikke blot en installationsnote.

## Obligatoriske domæner

- bogføring og postering,
- audit trail og integritet,
- digital bilagsopbevaring,
- standardkontoplan og momsmapping,
- SAF-T 2.1,
- bankimport/afstemning,
- OIOUBL/Peppol/NemHandel,
- myndighedsrapportering,
- backup, restore, retention og sikkerhedsdrift.

## Udenfor produktgrænsen

Som udgangspunkt er følgende ikke dækket af compliance-garantien:

- vilkårlige tredjepartsmoduler,
- direkte ændringer i Dolibarr core,
- ikke-understøttet hosting,
- databaseændringer uden for officielle migrations,
- integrationer som ikke er eksplicit dokumenteret som understøttede.

## Første runtime-baseline

- Dolibarr 24.0.x
- CI/integration Docker pin: 24.0.1
- PHP 8.2
- MariaDB 11.4
- managed cloud
- PostgreSQL og generisk self-hosting er uden for første registrerede produktgrænse

## Åbne beslutninger

- konkret referencehosting/cloudleverandør,
- e-fakturerings-/access-point-leverandør,
- backup- og object-storage-platform.
