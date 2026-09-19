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
| DK-ACC-006 | Bogførte transaktioner kan ikke ændres | DB Guard + PostingGuard | CT-ACC-006 | DONE |
| DK-ACC-007 | Bogførte transaktioner kan ikke slettes | DB Guard + PostingGuard | CT-ACC-007 | DONE |
| DK-ACC-008 | Rettelser sker sporbar via ny/modgående postering | CorrectionService | CT-ACC-008 | PARTIAL |
| DK-DOC-001 | Digitale bilag kan knyttes til bogføringen | Documents | CT-DOC-001 | TODO |
| DK-DOC-002 | Bilag og bogføringsdata kan opbevares iht. retentionkrav | Runtime | CT-DOC-002 | TODO |
| DK-AUD-001 | Compliance-relevant audit trail er append-only/logisk uforanderlig | AuditLedger | CT-AUD-001 | PARTIAL |
| DK-COA-001 | Understøttelse af offentlig standardkontoplan eller mapping | AccountMapping | CT-COA-001 | PARTIAL |
| DK-VAT-001 | Understøttelse/mapping af relevante momskoder | VatMapping + VAT provenance | CT-VAT-001 | PARTIAL |
| DK-SAFT-001 | SAF-T 2.1 kan eksporteres | SAF-T | CT-SAFT-001 | PARTIAL |
| DK-SAFT-002 | SAF-T 2.1 kan importeres | SAF-T Importer + staging | CT-SAFT-002 | PARTIAL |
| DK-SAFT-003 | SAF-T 2.1-output valideres mod officiel XSD | SAF-T Validator | CT-SAFT-003 | DONE |
| DK-SAFT-004 | SAF-T implementation er versionsstyret | SchemaRegistry + pinned ERST upstream | CT-SAFT-004 | DONE |
| DK-BANK-001 | Banktransaktioner kan importeres | bankconnect: CamtParser | CT-BANK-001 | PARTIAL |
| DK-BANK-002 | Bankposter kan afstemmes | bankconnect: ReconciliationEngine + MistralMatcher | CT-BANK-002 | PARTIAL |
| DK-BANK-003 | Ikke-afstemte differencer fremgår tydeligt | bankconnect: UI (afstemningsskærm) | CT-BANK-003 | TODO |
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


## Aktuel evidens for SAF-T/VAT-slicen

Følgende automatiserede beviser er nu grønne på Dolibarr 24.0.1:

- officiel ERST VAT JSON parses og versions-/gyldighedsdata bevares,
- 72 officielle momskoder importeres fra den pinned ERST-kilde,
- lokal Dolibarr-momskode mappes effective-dated til offentlig dansk momskode,
- canonical ledger lines kan bære flere VAT provenance records,
- en reel Dolibarr-kundefaktura rekonstrueres til VAT provenance på den relevante omsætningslinje,
- SAF-T 2.1 `TaxInformation` genereres uden at splitte faktiske GL-linjer,
- både fixture-output og output fra den reelle Dolibarr-provider validerer mod den pinned officielle ERST 2.1-XSD.

`DK-SAFT-003` og `DK-SAFT-004` er derfor teknisk testet og markeret `DONE`.
Det betyder ikke, at den samlede SAF-T-compliance er færdig: `DK-SAFT-001` forbliver
`PARTIAL`, indtil hele det relevante eksportscope er dækket. SAF-T import
(`DK-SAFT-002`) er implementeret for det beskrevne scope, men forbliver `PARTIAL`,
indtil hele det relevante imports scope er dækket.

VAT-mapping/provenance (`DK-VAT-001`) forbliver `PARTIAL`, fordi kunde- og
leverandørfakturaer er de første implementerede kilder; øvrige momsrelevante
dokumenttyper skal vurderes og testes separat.

### Testevidens

- `tests/integration/validate-official-vat-list.php`
- `tests/integration/validate-saft21.php`
- `tests/integration/assert-tax-provenance.php`
- `tests/integration/assert-saft21-dolibarr-provider.php`
- `tests/integration/test-bookkeeping-immutability.sh`
- ERST upstream commit: `ea9a4b5704c7a0e9646b0d3b928a59089d71cf0e`

## Aktuel evidens for SAF-T import-slicen

Følgende er implementeret og testet på Dolibarr 24.0.1:

- SAF-T 2.1 valideres mod den pinned officielle ERST-XSD før parsing,
- Header, GeneralLedgerAccounts, TaxTable og GeneralLedgerEntries parses,
- transaktioner konverteres til den eksisterende canonical accounting model,
- dublette AccountID, TransactionID og RecordID afvises,
- transaktioner skal balancere,
- deklareret NumberOfEntries, TotalDebit og TotalCredit kontrolleres mod faktisk indhold,
- importerede filer stages med SHA-256, så identiske filer ikke kan stages to gange,
- importerede konti analyseres mod lokale konti og effective-dated standardkontomapping,
- uafklarede eller tvetydige kontomappings blokerer Apply,
- Apply bogfører atomisk gennem Dolibarrs bogførings-API,
- importerede posteringer låses og får database- og audit-proveniens,
- genanvendelse af samme import blokeres,
- importerede momskoder bevares gennem roundtrip eksport og officiel XSD-validering.

`DK-SAFT-002` forbliver `PARTIAL`, indtil hele det relevante imports scope er dækket.

## Bank-afstemnings-slice (bankconnect)

Modulet er implementeret som selvstændigt Dolibarr-modul i
[smoeberg/bankconnect](https://github.com/smoeberg/bankconnect), CI-verificeret
(php -l + 21 unit tests, 59 assertions).

- `DK-BANK-001` → PARTIAL: camt.053/054-parsing implementeret og testet
  (`CamtParserTest`). Import via bank-PSD2/gateway er ikke tilsluttet endnu.
- `DK-BANK-002` → PARTIAL: regelbaseret matching (reference → beløb →
  datovindue) + Mistral AI fallback implementeret og testet
  (`ReconciliationEngineTest`, `MistralMatcherTest`). Bokføring sker først
  efter menneskelig godkendelse.
- `DK-BANK-003` → TODO: afstemningsskærm (UI) mangler.

Testevidens: `tests/unit/` i bankconnect-repoet; CI-workflow
`.github/workflows/test.yml` på push/PR.

GDPR-kontrol: MistralMatcher saniterer CPR-numre og lange numeriske koder
frem afkald på sende følsomme data til AI, og logger kun metrikker (hash,
latency, antal) - aldrig statement-tekst.
