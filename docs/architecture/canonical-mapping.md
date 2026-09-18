# Dolibarr -> Canonical Accounting mapping

Initial baseline: Dolibarr 24.0.x.

| Canonical field | Dolibarr source |
|---|---|
| transactionId | `accounting_bookkeeping.ref`, fallback `piece_num` |
| sourcePieceNumber | `piece_num` |
| journalCode | `code_journal` |
| transactionDate | `doc_date` |
| registrationDateTime | earliest `date_creation` in piece |
| validatedAt | null unless every line is validated; latest validation timestamp when fully validated |
| actor | `user:<fk_user_author>` |
| sourceType | `doc_type` |
| documentRef | `doc_ref` |
| lineId | bookkeeping `rowid` |
| accountCode | `numero_compte` |
| debit | `debit` |
| credit | `credit` |
| partyId | `subledger_account` |
| currencyCode | `multicurrency_code` |
| currencyAmount | `multicurrency_amount` |
| description | `label_operation` |

## Consistency rules

Rows with the same `piece_num` form one canonical transaction.

The adapter refuses a piece when its rows disagree on:

- journal code,
- document date,
- author,
- document type,
- document reference.

This is deliberate. An inconsistent source transaction is a data/compliance defect and must not be silently normalised.

## Validation state

A canonical transaction is marked validated only if every Dolibarr row in the piece has a non-null validation timestamp.

If the rows were locked at slightly different times, the latest timestamp is retained as the canonical lock time.

## Decimal handling

Dolibarr bookkeeping uses 8 decimal places. Values are immediately normalised into canonical fixed-scale decimal strings before arithmetic or export.

SAF-T-specific formatting belongs in the SAF-T mapping layer, not in this adapter.
