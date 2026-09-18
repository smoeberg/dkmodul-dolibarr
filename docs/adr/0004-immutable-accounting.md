# ADR-0004 — Immutable Posted Accounting

Status: Accepted

## Beslutning

Bogførte/posterede transaktioner behandles som immutable.

Efter postering må compliance-relevante felter ikke ændres eller slettes gennem understøttede applikationsflows.

Rettelser skal ske via sporbare mod-/korrektionsposteringer.

## Tekniske mål

- central PostingGuard,
- audit event ved postering,
- audit event ved afvist ændrings-/sletteforsøg,
- relation mellem original post, reversal og evt. erstatningspost,
- automatiserede compliance-tests.
