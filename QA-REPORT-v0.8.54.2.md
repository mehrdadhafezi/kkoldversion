# QA Report v0.8.54.2

## Version
- Exact plugin version: `0.8.54.2`
- Patch name: Reconciliation Release Safety Hardening
- Base package SHA256: `7C69515D9C63300106FE72DF544DB802880475288E90D92A552DE4FF43786169`
- Mode: `READ_ONLY`

## Fixes
- Historical manual merge detection now scans and indexes all `merge_events` rows, then marks a guarded customer as protected when it appears either as `primary_customer_id` or anywhere in `merged_customer_ids`. This no longer depends on chunk membership, primary membership in the Guard scope, or Run #7 cutoff.
- CSV export now neutralizes formula-prefixed textual values only at CSV serialization time. JSON and deterministic report hashing are unchanged.
- Phone ownership evidence now preserves a complete deterministic owner set per normalized phone instead of choosing the first row.
- Invoice/phone `NOT_APPLICABLE` states now require absence of direct source evidence and absence of linked evidence that the reconciliation can inspect.

## Manual Merge Algorithm
`manual_merge_by_customer()` initializes one output row per current Guard ID, loads all `merge_events` rows ordered by `id`, parses `primary_customer_id` and `merged_customer_ids`, intersects that complete related-ID set with the current Guard set, and marks every matched guarded customer as `present`. Rows with a human `performed_by`, post-Run-7 evidence, or malformed related-ID data affecting a guarded row are treated as conflicts, causing conservative `MANUAL_REVIEW_REQUIRED` classification through the existing fail-closed classifier.

No merge rows are inserted, updated, deleted, or rewritten.

## CSV Formula Injection Protection
`csv_value()` now calls `neutralize_csv_formula()` at the export boundary. Any string whose first meaningful character, after leading whitespace/control characters, is `=`, `+`, `-`, or `@` is prefixed with a single quote. Database values, JSON export values, and report hash inputs are not changed.

## Duplicate Phone Owner Handling
`phone_owners_by_value()` now returns `normalized_phone => [owner rows...]`, deduplicated by owner customer ID and sorted deterministically. Invoice and linked phone evidence evaluate every owner. Any incompatible owner fails evidence; unresolved owner attribution becomes `UNKNOWN` where applicable.

## NOT_APPLICABLE Semantics
Invoice evidence returns `NOT_APPLICABLE` only when there are no source-owned invoices and no linked invoice usage through source phone values. Phone evidence returns `NOT_APPLICABLE` only when there are no source-owned phones and no linked invoice-phone values from source-owned invoices. If linked evidence exists, it is evaluated as `PASS`, `FAIL`, or `UNKNOWN`.

## Read-only Call Graph Proof
The v0.8.54.2 reconciliation entry points still call only local read/evaluation/export helpers. Reachable SQL remains limited to `SELECT`, `SHOW TABLES`, and `SHOW COLUMNS`, plus the existing per-admin report transient write. No customer, invoice, phone, invoice_phone, activity, assignment, interaction, complaint, case, tag, merge, audit, Guard, reconstruction run, or Run #7 journal mutation is introduced.

Allowed write: per-admin WordPress transient only.

## Regression Tests
The v0.8.54.2 regression runner covers:
- 18 required classification cases.
- historical `merged_customer_ids` detection cases.
- duplicate phone owner evidence cases.
- CSV formula neutralization cases.
- hardened `NOT_APPLICABLE` cases.

Actual execution result:

`TOTAL_PASS=49 TOTAL_FAIL=0`

## Validation
Final release validation must be performed against the packaged ZIP after extraction:
- `php -l` against all shipped PHP files.
- `node --check` against all shipped JS files.
- static mutation audit of reachable v0.8.54.2 reconciliation call graph.

This patch does not implement Run #8 and does not approve Production installation.
