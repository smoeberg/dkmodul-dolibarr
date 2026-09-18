# ADR-0002 — Hosting Model

Status: Proposed

## Beslutning

Hosting/drift skal vælges og dokumenteres før compliance-kritisk implementation færdiggøres.

Første release bør have én referencehostingprofil med:

- EU/EØS-baseret primær drift eller dokumenteret relevant placering,
- krypteret database og dokumentlager,
- separat backupmekanisme,
- daglige og ugentlige backupjobs,
- sekundær backupkopi,
- dokumenteret restoreprocedure,
- centraliseret logning og monitorering,
- kontrolleret adminadgang.

## Åbent

Leverandør og konkret platform er endnu ikke valgt.

## Konsekvens

Runtime-interfaces og compliance dashboard skal kunne verificere backup-, storage- og restore-status.
