# ADR-0007 — Correction postings

Status: Accepted

## Context

A finally posted bookkeeping transaction must not be edited, backdated or deleted. Corrections must be represented by new postings.

Dolibarr already has a "ReturnAccount"/extourne workflow, but the upstream implementation identifies reversals largely through generated text and does not persist a durable relationship between the original transaction and its correction.

## Decision

Dolibarr DK models corrections explicitly at accounting transaction level (`piece_num`).

A correction relationship records:

- original transaction (`original_piece_num`),
- correcting transaction (`correction_piece_num`),
- relation type,
- reason,
- actor,
- creation timestamp.

Supported relation types initially:

- `reversal` — full reversing posting,
- `adjustment` — correcting difference,
- `replacement` — corrected replacement transaction.

The original transaction remains unchanged.

## Consequences

- correction history is queryable without parsing labels;
- SAF-T/reporting can explain correction relationships deterministically;
- the compliance UI can navigate original -> correction(s);
- controlled DK correction workflows must record this relation when generating new postings;
- raw upstream extourne remains usable only after it is integrated with this relation model or disabled in compliance mode.
