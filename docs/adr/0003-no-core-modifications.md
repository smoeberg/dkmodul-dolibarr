# ADR-0003 — No Dolibarr Core Modifications by Default

Status: Accepted

## Beslutning

Dolibarr core ændres ikke som udgangspunkt.

Implementation skal ske via:

- modulmekanismer,
- hooks,
- triggers,
- permissions,
- egne tabeller,
- services/API-adapters.

## Undtagelse

Hvis et dokumenteret compliancekrav ikke kan håndhæves sikkert gennem de officielle extension points, skal en særskilt ADR beskrive behov, risiko og vedligeholdelsesstrategi for en core-ændring.
