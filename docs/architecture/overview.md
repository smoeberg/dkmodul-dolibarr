# Arkitekturoversigt

## Mål

Dolibarr DK skal separere Dolibarr-specifik funktionalitet fra dansk compliance-logik, så ændringer i Dolibarr eller danske standarder kan håndteres isoleret.

## Hovedkomponenter

```text
Dolibarr Core
    |
    v
DK Integration Layer
    |
    +-- Accounting Guard
    +-- Audit Ledger
    +-- Document/Retention
    +-- Standard Account/VAT Mapping
    +-- Canonical Accounting Model
    +-- SAF-T 2.1
    +-- Bank/Reconciliation
    +-- E-Invoice Adapter
    +-- Reporting
    |
    v
DK Certified Runtime
    |
    +-- Database
    +-- Object/document storage
    +-- Backup/restore
    +-- Monitoring/logging
    +-- External gateways
```

## Arkitekturprincipper

- Ingen hard dependency fra SAF-T på Dolibarr SQL-tabeller.
- Dolibarr-data oversættes til en canonical accounting model.
- Compliance-critical state transitions håndhæves centralt.
- Eksterne integrationsleverandører abstraheres bag interfaces.
- Schemaer, mappingtabeller og standardkontoplaner versionsstyres.
- Hostingkrav dokumenteres som en del af systemdesign.
