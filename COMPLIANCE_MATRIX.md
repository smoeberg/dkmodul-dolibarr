# Compliance Matrix — Dolibarr DK

Statuskoder:

- `TODO` — ikke analyseret/implementeret
- `DESIGN` — arkitektur besluttet, implementation mangler
- `PARTIAL` — delvist dækket
- `DONE` — implementeret og testet

| ID | Krav | Komponent | Test-ID | Status |
|---|---|---|---|---|
| DK-ACC-001 | Transaktionsdato registreres | Accounting | CT-ACC-001 | DONE |
| DK-ACC-002 | Beløb, tekst og bilagsreference registreres | Accounting | CT-ACC-002 | DONE |
| DK-ACC-003 | Fortløbende/entydig identifikation af postering | Accounting | CT-ACC-003 | DONE |
| DK-ACC-004 | Registreringsdato registreres | Audit | CT-ACC-004 | DONE |
| DK-ACC-005 | Bruger/program bag registrering kan identificeres | Audit | CT-ACC-005 | DONE |
| DK-ACC-006 | Bogførte transaktioner kan ikke ændres | DB Guard + PostingGuard | CT-ACC-006 | DONE |
| DK-ACC-007 | Bogførte transaktioner kan ikke slettes | DB Guard + PostingGuard | CT-ACC-007 | DONE |
| DK-ACC-008 | Rettelser sker sporbar via ny/modgående postering | CorrectionService | CT-ACC-008 | DONE |
| DK-DOC-001 | Digitale bilag kan knyttes til bogføringen | Documents | CT-DOC-001 | DONE |
| DK-DOC-002 | Bilag og bogføringsdata kan opbevares iht. retentionkrav | Runtime | CT-DOC-002 | PARTIAL |
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
| DK-EINV-001 | OIOUBL faktura kan sendes | EInvoice | CT-EINV-001 | PARTIAL |
| DK-EINV-002 | OIOUBL faktura kan modtages | EInvoice | CT-EINV-002 | PARTIAL |
| DK-EINV-003 | OIOUBL kreditnota kan sendes/modtages | EInvoice | CT-EINV-003 | DONE |
| DK-EINV-004 | Relevante OIOUBL-responsmeddelelser understøttes | EInvoice | CT-EINV-004 | DONE |
| DK-EINV-005 | Peppol BIS faktura kan sendes/modtages | EInvoice | CT-EINV-005 | TODO |
| DK-EINV-006 | Peppol BIS kreditnota kan sendes/modtages | EInvoice | CT-EINV-006 | TODO |
| DK-EINV-007 | Relevante Peppol-responsmeddelelser understøttes | EInvoice | CT-EINV-007 | TODO |
| DK-NHR-001 | Kunde kan informeres om/tilmeldes NemHandelsregister | EInvoice | CT-NHR-001 | TODO |
| DK-REP-001 | Regnskab Basis CSV eller andet accepteret årsrapportformat | Reporting | CT-REP-001 | TODO |
| DK-VATAPI-001 | Momsindberetning via Skattestyrelsens API understøttes | Reporting | CT-VATAPI-001 | TODO |
| DK-OPS-001 | Daglig inkrementel backup kan dokumenteres | BackupComplianceMonitor | CT-OPS-001 | PARTIAL |
| DK-OPS-002 | Ugentlig fuld backup kan dokumenteres | BackupComplianceMonitor | CT-OPS-002 | PARTIAL |
| DK-OPS-003 | Mindst én fuld og inkrementel backupkopi i EU/EØS kan dokumenteres | BackupComplianceMonitor | CT-OPS-003 | PARTIAL |
| DK-OPS-004 | Restoreprocedure er dokumenteret og testbar | BackupComplianceMonitor | CT-OPS-004 | PARTIAL |
| DK-SEC-001 | Adgangsstyring er dokumenteret | Security | CT-SEC-001 | TODO |
| DK-SEC-002 | Sikkerhedslogning er dokumenteret | Security | CT-SEC-002 | TODO |
| DK-SEC-003 | Netværks- og driftskontroller er dokumenteret | Security | CT-SEC-003 | TODO |
| DK-P0-001 | Produkt- og komponentversioner er maskinlæsbart fastlåst | Product manifest | CT-P0-001 | DONE |
| DK-P0-002 | Registreret profil fejler lukket ved deaktiveret compliance | ComplianceLock | CT-P0-002 | DONE |
| DK-P0-003 | Normal afinstallation kan ikke fjerne kontroller i registreret profil | ComplianceLock | CT-P0-003 | DONE |
| DK-P0-004 | Hosting-/backup-part og EU/EØS-kopi attesteres pr. installation | Deployment attestation | CT-P0-004 | PARTIAL |
| DK-P0-005 | Eget access point er certificeret og driftsdokumenteret | Access point | CT-P0-005 | TODO |

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

## Kontrollerede rettelser

`DK-ACC-008` er dækket af et atomisk korrektionsflow på Dolibarr 24.0.1:

- den validerede original låses og forbliver uændret,
- en balanceret reversal oprettes gennem Dolibarrs bogførings-API,
- debit og kredit vendes linje for linje,
- de nye rækker valideres før commit,
- relationstype, årsag og aktør gemmes i `dk_correction`,
- et hash-kædet audit-event oprettes i samme transaktion,
- endnu en reversal af samme original blokeres.

Testevidens: `tests/integration/assert-correction-workflow.php` og
`tests/integration/test-bookkeeping-immutability.sh`.

## Audit ledger-integritet

Audit events er databasebeskyttet mod UPDATE og DELETE, og en unik
`(entity, previous_hash)`-nøgle forhindrer parallelle kædeforgreninger.
Integrationstesten afviser direkte manipulation og en dublet forgænger, hvorefter
`DkAuditLedger::verifyChain()` verificerer hele kæden.

`DK-AUD-001` forbliver `PARTIAL`, indtil alle compliance-relevante write-paths
udsender de nødvendige events, og den operationelle kontrol/rapportering er
defineret.

Testevidens: `tests/integration/assert-audit-ledger.php` og
`tests/integration/test-bookkeeping-immutability.sh`.

## Grundlæggende bogføringsevidens

`DK-ACC-001–005` er testet gennem Dolibarr 24.0.1's `BookKeeping`-API:

- transaktionsdato, beløb, posteringstekst og bilagsreference genlæses fra databasen,
- en balanceret bevægelse får næste fortløbende `piece_num` og en ikke-tom reference,
- `date_creation` beviser registreringstidspunktet,
- `fk_user_author`, databasebrugeren, provenance-hash og audit-event identificerer
  bruger og write-path,
- den validerede postering afviser efterfølgende API-opdatering både med normale
  applikationstriggers og med `notrigger=1`.

Testevidens: `tests/integration/assert-core-bookkeeping-evidence.php` og
`tests/integration/test-bookkeeping-immutability.sh`.

## Digitale bilag og retention

`DK-DOC-001` er dækket af et content-addressed dokumentarkiv, der knytter de
arkiverede bytes entydigt til en eksisterende bogføringsrække. SHA-256, størrelse,
kildereference, aktør og opbevaringsfrist registreres, og arkiveringen skrives til
det hash-kædede audit ledger. Metadata afviser UPDATE og DELETE på databaseniveau,
og arkivets bytes kan efterfølgende verificeres mod hash og størrelse.

`DK-DOC-002` er `PARTIAL`: applikationslaget beregner fem år fra regnskabsårets
udgang og tilbyder ingen slettevej, men deployment-evidens for backup, restore,
redundans og adgang efter abonnementsophør hører til den endnu åbne runtime-slice.

Testevidens: `tests/integration/assert-document-archive.php` og
`tests/integration/test-bookkeeping-immutability.sh`.

## OIOUBL outbound faktura

En reel Dolibarr-kundefaktura mappes til en immutable, transport-neutral
fakturemodel og derfra deterministisk til OIOUBL XML. XML'en valideres mod den
pinnede officielle UBL 2.1-XSD og OIOUBL Invoice Schematron, hvorefter de præcise
validerede bytes arkiveres med hash, retention og kobling til bogføringen.

En transport-neutral outbox sender kun hash-verificerede bytes fra det immutable
arkiv. Stabil idempotency key for dokument, modtager og transport forhindrer
dobbeltlevering efter en accepteret kvittering. Forsøg og normaliserede
providerkvitteringer gemmes som append-only events og kobles til AuditLedger.

`DK-EINV-001` er fortsat `PARTIAL`: den deterministiske adapter i CI beviser
transportgrænsen og kvitteringskæden, men en live understøttet NemHandel/Peppol-
connector med credentials, endpoint discovery og operationel overvågning mangler.

Testevidens: `tests/unit/OioUblInvoiceGeneratorTest.php`,
`tests/integration/assert-oioubl-outbound.php` og
`tests/integration/assert-einvoice-transport.php` samt
`tests/integration/test-bookkeeping-immutability.sh`. Officiel ERST-kilde:
`openebusiness/common@223694e79eb4dbf0895640b35484ab55abae2c42`.

## OIOUBL inbound staging

Inbound OIOUBL gemmes byte-identisk i et content-addressed stagingområde før
fortolkning. Kanalens message ID giver idempotent redelivery, mens genbrug af
samme identitet med andre bytes afvises. Først efter officiel XSD- og
Schematron-validering udtrækkes faktura-, endpoint- og beløbsidentitet.
Valideringsresultat og artefakthashes er append-only og AuditLedger-koblede.

`DK-EINV-002` er `PARTIAL`, fordi staging, teknisk validering, mapping til
leverandør, kladdeoprettelse og eksplicit forretningsgodkendelse via Dolibarrs
validerings-API samt kontrolleret, balanceret overførsel til hovedbogen nu er
dækket. Et rettighedsopdelt brugerflow viser den evidensafledte status og kalder
de kontrollerede handlinger uden at kunne springe et trin over. Fremmed valuta
og lokale afgifter mangler fortsat.

Testevidens: `tests/integration/assert-oioubl-inbound-staging.php` og
`tests/integration/test-bookkeeping-immutability.sh`.

En valideret inbound faktura kan matches entydigt til en aktiv leverandør via
dansk CVR og efter eksplicit aktørgodkendelse oprettes som Dolibarr-
leverandørfakturakladde gennem standard-API'et. Immutable provenance forbinder
staging, leverandør og kladde; retry genbruger samme kladde, og der bogføres ikke.

En separat, autoriseret handling genkontrollerer de arkiverede bytes, CVR,
reference, total, entity og kladdestatus, før Dolibarr validerer fakturaen.
Valideringsevidensen er append-only og retry er idempotent. Dokumentvalideringen
overfører bevidst ikke poster til `accounting_bookkeeping`; denne grænse er
beskrevet i ADR-0018.

Efter dokumentvalideringen kan en særskilt autoriseret handling overføre fakturaen
til et eksplicit købsjournal. Leverandør-, købskonto- og momskontomapping kommer
fra Dolibarrs native accountingfelter og skal være komplette og entydige.
Bevægelsen kontrolleres i øre, genlæses efter oprettelse gennem
`BookKeeping::createStd()` og låses først, når alle linjer har samme piece number
og debit er lig credit og fakturatotalen. Posting-evidens og bogføringslinjer er
derefter immutable; grænsen er beskrevet i ADR-0019.

Testevidens: `tests/integration/assert-oioubl-inbound-supplier-draft.php` og
`tests/integration/assert-oioubl-inbound-supplier-validation.php`.
Posting dækkes af `tests/integration/assert-oioubl-inbound-supplier-posting.php`.
Det samlede brugerflow dækkes af
`tests/integration/assert-oioubl-inbound-workflow.php` og ADR-0020.

Inbound OIOUBL-kreditnotaer genbruger samme kontrollerede flow. En obligatorisk
fakturareference bindes til én valideret leverandørfaktura hos samme leverandør,
Dolibarr opretter sin native kreditnotatype, og hovedbogsoverførslen vender køb,
indgående moms og leverandørgæld i et eksakt balanceret bilag. Omfang og grænser
er beskrevet i ADR-0021 og dækket af
`tests/integration/assert-oioubl-inbound-credit-note.php`.

Outbound OIOUBL-kreditnotaer genereres nu fra validerede native Dolibarr-
kundekreditnotaer med obligatorisk reference til en valideret originalfaktura hos
samme kunde. Beløb normaliseres til positive UBL-dokumentbeløb, output valideres
mod den officielle CreditNote-XSD og Schematron, arkiveres byte-identisk og
leveres gennem den idempotente transportgrænse. Sammen med inbound-flowet gør
dette `DK-EINV-003` til `DONE` for det definerede OIOUBL-scope. Beslutningen er
beskrevet i ADR-0022 og dækket af
`tests/integration/assert-oioubl-outbound-credit-note.php`.

OIOUBL `ApplicationResponse` modtages som en separat, officiel XSD- og
Schematron-valideret evidensstrøm. Afsender/modtager samt dokumentets ID, UUID og
type skal matche den byte-verificerede arkiverede outbound faktura eller
kreditnota. Provider-identiteten er idempotent, genbrug med andre bytes afvises,
og både original XML, valideringshashes, binding og audit-event er immutable.
Dette adskiller transportkvittering fra teknisk og forretningsmæssig accept eller
afvisning og gør `DK-EINV-004` til `DONE` for OIOUBL ApplicationResponse-scope.
Beslutningen er beskrevet i ADR-0023 og dækket af
`tests/integration/assert-oioubl-application-response.php`.
