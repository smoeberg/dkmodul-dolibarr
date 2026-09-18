# ADR-0006 — Supported runtime baseline

Status: Accepted

## Decision

The initial Dolibarr DK certification/development baseline is:

- Dolibarr 24.0.x,
- test pin: Dolibarr 24.0.1,
- PHP 8.2,
- MariaDB 11.4,
- DK module installed as an external module,
- managed-cloud product profile from ADR-0002.

## Rationale

Certification needs a reproducible system boundary. Supporting several Dolibarr majors and database engines from day one multiplies the compliance test surface without improving the first registration.

## Upgrade policy

A newer Dolibarr patch/minor/major is not automatically inside the supported product boundary.

Before approval it must pass:

1. module syntax/unit tests,
2. accounting table schema diff,
3. bookkeeping write-path inventory,
4. database immutability integration test,
5. full compliance regression suite,
6. manual review of upstream accounting changes.

The pinned integration-test version may then be advanced through a reviewed pull request.
