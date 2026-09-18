# Compliance Matrix — Dolibarr DK

Statuskoder:

- `TODO` — ikke analyseret/implementeret
- `DESIGN` — arkitektur besluttet, implementation mangler
- `PARTIAL` — delvist dækket
- `DONE` — implementeret og testet

| ID | Krav | Komponent | Test-ID | Status |
|---|---|---|---|---|
| DK-ACC-001 | Transaktionsdato registreres | Accounting | CT-ACC-001 | TODO |
| DK-ACC-002 | Beløb, tekst og bilagsreference registreres | Accounting | CT-ACC-002 | TODO |
| DK-ACC-003 | Fortløbende/entydig identifikation af postering | Accounting | CT-ACC-003 | TODO |
| DK-ACC-004 | Registreringsdato registreres | Audit | CT-ACC-004 | TODO |
| DK-ACC-005 | Bruger/program bag registrering kan identificeres | Audit | CT-ACC-005 | TODO |
| DK-ACC-006 | Bogførte transaktioner kan ikke ændres | PostingGuard | CT-ACC-006 | PARTIAL |
| DK-ACC-007 | Bogførte transaktioner kan ikke slettes | PostingGuard | CT-ACC-007 | PARTIAL |
| DK-ACC-008 | Rettelser sker sporbar via ny/modgående postering | Accounting | CT-ACC-008 | TODO |
| DK-DOC-001 | Digitale bilag kan knyttes til bogføringen | Documents | CT-DOC-001 | TODO |
| DK-DOC-002 | Bilag og bogføringsdata kan opbevares iht. retentionkrav | Runtime | CT-DOC-002 | TODO |
| DK-AUD-001 | Compliance-relevant audit trail er append-only/logisk uforanderlig | AuditLedger | CT-AUD-001 | PARTIAL |
| DK-COA-001 | Understøttelse af offentlig standardkontoplan eller mapping | AccountMapping | CT-COA-001 | TODO |
| DK-VAT-001 | Understøttelse/mapping af relevante momskoder | VatMapping | CT-VAT-001 | TODO |
| DK-SAFT-001 | SAF-T 2.1 kan eksporteres | SAF-T | CT-SAFT-001 | TODO |
| DK-SAFT-002 | SAF-T 2.1 kan importeres | SAF-T | CT-SAFT-002 | TODO |
| DK-SAFT-003 | SAF-T 2.1-output valideres mod officiel XSD | SAF-T | CT-SAFT-003 | TODO |
| DK-SAFT-004 | SAF-T implementation er versionsstyret | SAF-T | CT-SAFT-004 | DESIGN |
| DK-BANK-001 | Banktransaktioner kan importeres | Bank | CT-BANK-001 | TODO |
| DK-BANK-002 | Bankposter kan afstemmes | Bank | CT-BANK-002 | TODO |
| DK-BANK-003 | Ikke-afstemte differencer fremgår tydeligt | Bank | CT-BANK-003 | TODO |
| DK-EINV-001 | OIOUBL faktura kan sendes | EInvoice | CT-EINV-001 | TODO |
| DK-EINV-002 | OIOUBL faktura kan modtages | EInvoice | CT-EINV-002 | TODO |
| DK-EINV-003 | OIOUBL kreditnota kan sendes/modtages | EInvoice | CT-EINV-003 | TODO |
| DK-EINV-004 | Relevante OIOUBL-responsmeddelelser understøttes | EInvoice | CT-EINV-004 | TODO |
| DK-EINV-005 | Peppol BIS faktura kan sendes/modtages | EInvoice | CT-EINV-005 | TODO |
| DK-EINV-006 | Peppol BIS kreditnota kan sendes/modtages | EInvoice | CT-EINV-006 | TODO |
| DK-EINV-007 | Relevante Peppol-responsmeddelelser understøttes | EInvoice | CT-EINV-007 | TODO |
| DK-NHR-001 | Kunde kan informeres om/tilmeldes NemHandelsregister | EInvoice | CT-NHR-001 | TODO |
| DK-REP-001 | Regnskab Basis CSV eller andet accepteret årsrapportformat | Reporting | CT-REP-001 | TODO |
| DK-VATAPI-001 | Momsindberetning via Skattestyrelsens API understøttes | Reporting | CT-VATAPI-001 | TODO |
| DK-OPS-001 | Daglig inkrementel backup kan dokumenteres | Runtime | CT-OPS-001 | DESIGN |
| DK-OPS-002 | Ugentlig fuld backup kan dokumenteres | Runtime | CT-OPS-002 | DESIGN |
| DK-OPS-003 | Mindst én fuld og inkrementel backupkopi i EU/EØS kan dokumenteres | Runtime | CT-OPS-003 | DESIGN |
| DK-OPS-004 | Restoreprocedure er dokumenteret og testbar | Runtime | CT-OPS-004 | TODO |
| DK-SEC-001 | Adgangsstyring er dokumenteret | Security | CT-SEC-001 | TODO |
| DK-SEC-002 | Sikkerhedslogning er dokumenteret | Security | CT-SEC-002 | TODO |
| DK-SEC-003 | Netværks- og driftskontroller er dokumenteret | Security | CT-SEC-003 | TODO |

## Kilder

Primære kilder for videre validering og kravnedbrydning:

- BEK nr. 97 af 26/01/2023 om krav til digitale standardbogføringssystemer.
- BEK nr. 98 af 26/01/2023 om registrering af digitale standardbogføringssystemer.
- Erhvervsstyrelsens vejledninger om registrerede bogføringssystemer.
- Erhvervsstyrelsens repository for standardfilformater, herunder SAF-T og tilhørende schemaer.

Matrixen skal udbygges med præcis paragraf-/kildereference for hvert krav før krav mærkes som `DONE`.


## Verificerede kildereferencer

- **BEK 97/2023 §6 og bilag 1**: bogføring, kontrolspor, bilagsopbevaring og 5 års opbevaring.
- **BEK 97/2023 §7, stk. 1-3**: mindst ugentlig fuld backup, mindst daglig inkrementel backup og mindst én fuld og inkrementel kopi på server i EU/EØS.
- **BEK 97/2023 §8**: høj IT-sikkerhed for cloud-baserede standardsystemer og løbende risikovurdering.
- **BEK 98/2023 §8**: anmeldelsesoplysninger og dokumentation; produktets cloud/hybrid-model og opbevaringspart indgår i anmeldelsen.
- **Erhvervsstyrelsens “Standardkontoplan og SAF-T”, opdateret 8. september 2026**: registrerede bogføringssystemer skal fra 1. januar 2027 understøtte SAF-T 2.1; systemet skal kunne generere, importere og eksportere standardfilen.
