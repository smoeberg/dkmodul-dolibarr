# Inexchange – clarification questions before adapter commitment

**Subject:** Questions regarding Inexchange Partner API, Danish receiving setup and commercial/DPA terms

Hej Inexchange,

Vi er i gang med at integrere Inexchange som en konfigurerbar e-faktura/access-point-provider i vores danske ERP-modul. Vi har gennemgået jeres Partner API og kan se, at den teknisk passer godt til vores arkitektur.

Inden vi låser første adapter og går videre mod produktionssetup, vil vi gerne have afklaret følgende:

## 1. DocumentFormat og formatansvar

I `POST /documents/outbound` angives `DocumentFormat` som en streng.

Kan I bekræfte:

- de præcise `DocumentFormat`-værdier, der skal bruges for OIOUBL 2.02;
- de præcise værdier for Peppol BIS Billing 3.0;
- om Inexchange accepterer allerede validerede OIOUBL/Peppol BIS-dokumenter uden konvertering;
- hvornår Inexchange foretager formatkonvertering, og hvilke input/output-formater der kan vælges;
- om der findes en officiel formatliste eller schema-/API-reference, som vi bør implementere imod.

Vi vil gerne sikre, at vores ERP genererer og validerer dokumentet korrekt, og at adapteren ikke kommer til at afhænge af uofficielle formatnavne.

## 2. Automatisk aktivering af dansk NemHandel/Peppol-modtagelse

Vi kan se API-flowet for incoming documents og `documents/handled`.

Kan I bekræfte, om hele virksomhedens modtagelsesopsætning kan automatiseres via API'et, herunder:

- oprettelse/registrering af virksomheden;
- CVR-/virksomhedsidentifikation;
- aktivering af NemHandel/Peppol-modtagelse;
- tildeling/registrering af relevant elektronisk adresse/Peppol ID;
- valg af modtagne dokumenttyper;
- status på registreringen.

Hvis ikke hele flowet kan automatiseres, vil vi gerne vide præcis, hvilke trin der kræver login i portal eller manuel handling hos Inexchange/support.

Det er vigtigt for os, fordi vi ønsker, at vores settings-GUI kan vise en entydig onboarding-status og kun bede kunden om manuel handling, hvor det reelt er nødvendigt.

## 3. Partner API, DPA og kommercielle vilkår

Vi vil også gerne modtage information om:

- prisstruktur for Partner API'et;
- eventuelle minimumsvolumener eller faste partnergebyrer;
- test/sandbox og eventuelle begrænsninger;
- produktionsgodkendelse og onboardingproces;
- SLA/supportniveau og incident-håndtering;
- databehandleraftale (DPA) og relevante underdatabehandlere;
- hvor kundedata behandles og opbevares;
- gældende lovvalg/jurisdiktion for den danske løsning;
- data-/dokumenteksport ved ophør samt eventuelle exit-/portabilitetsmuligheder.

Hvis DPA og øvrige juridiske vilkår afhænger af den konkrete partneraftale, må I gerne sende den relevante partnerdokumentation.

På forhånd tak. Svarene skal bruges som input til vores tekniske adapterdesign samt vores compliance- og leverandørvurdering.

Med venlig hilsen

[Navn]
[Virksomhed]
[Kontaktoplysninger]
