# QA Report v0.8.54.1

## Version
- Exact plugin version: `0.8.54.1`
- Patch name: Evidence Consistency Hardening
- Base: `0.8.54`
- Mode: `READ_ONLY`

## Files Changed
- `biawin-crm-integration.php`
- `includes/class-biawin-crm-post-run7-reconciliation.php`
- `CHANGELOG.md`
- `readme.txt`
- `QA-REPORT-v0.8.54.1.md`

## Invoice Evidence Algorithm
The reconciliation still derives scope from live `bcrm_readiness_quarantine` rows. For those customer IDs it batch-loads:

- source-owned rows from `bcrm_invoices`
- `bcrm_invoice_phones` for those invoice IDs
- phone owner rows from `bcrm_phones` for the invoice phone values

Allowed identity owners are the current guarded source internal ID and the expected target internal ID derived from the Frozen Canonical Plan. If a source-owned invoice has an invoice phone whose canonical phone owner is another customer, the invoice state is `FAIL` with `INVOICE_OWNER_MISMATCH` and `INVOICE_EVIDENCE_CONFLICT`.

If an invoice was updated after the Run #7 cutoff but invoice actor attribution is unavailable in the schema, the state is `UNKNOWN` with `INVOICE_CHANGED_AFTER_RUN7_ATTRIBUTION_UNKNOWN`.

Zero source-owned invoices are `NOT_APPLICABLE`; this does not automatically fail the row.

## Phone Evidence Algorithm
The audit batch-loads source-owned rows from `bcrm_phones`, then batch-loads invoice usage for their `normalized_phone` values by joining `bcrm_invoice_phones` to `bcrm_invoices`.

Allowed invoice owners are the current guarded source internal ID and the expected target internal ID. If a source phone is attached to an invoice owned by another customer, the phone state is `FAIL` with `PHONE_OWNER_CONFLICT` and `PHONE_EVIDENCE_CONFLICT`.

Manual/explicit phone sources are treated as stronger evidence. The manual sources checked are:

- `agent`
- `manual`
- `manual_invoice_bundle`
- `manual_invoice_phone`

When a manual phone conflicts with an incompatible invoice owner, `MANUAL_PHONE_EVIDENCE_CONFLICT` is added and classification remains conservative. When manual phone evidence appears after Run #7 without actor attribution in the phone schema, the state is `UNKNOWN` with `PHONE_CHANGED_AFTER_RUN7_ATTRIBUTION_UNKNOWN`.

Zero source-owned phones are `NOT_APPLICABLE`; this does not automatically fail the row.

## Evidence State Semantics
- `PASS`: evidence exists and no incompatible ownership or post-Run-7 unattributed change was found.
- `FAIL`: evidence contradicts the expected source/target identity path.
- `UNKNOWN`: the audit cannot prove consistency, usually because required relation tables are missing or post-Run-7 changes lack actor attribution.
- `NOT_APPLICABLE`: no source evidence exists for that dimension, so the dimension does not by itself prove or disprove the canonical path.

Only `PASS` and `NOT_APPLICABLE` set the existing booleans `invoice_evidence_consistent` and `phone_evidence_consistent` to true.

## SECOND_PASS_SAFE Change
`SECOND_PASS_SAFE` now fails closed when either evidence state is `FAIL` or `UNKNOWN`. Evidence reason codes are merged into the row's `block_reason_codes`, and evidence-blocked rows classify conservatively as `MANUAL_REVIEW_REQUIRED` unless a stronger invariant block already requires `UNEXPECTED_BLOCK`.

## New Row Fields
- `invoice_evidence_state`
- `invoice_evidence_consistent`
- `invoice_evidence_reason_codes`
- `current_invoice_count`
- `current_invoice_codes`
- `conflicting_invoice_count`
- `invoice_evidence_detail` in JSON
- `phone_evidence_state`
- `phone_evidence_consistent`
- `phone_evidence_reason_codes`
- `current_phone_count`
- `current_phone_values`
- `conflicting_phone_count`
- `phone_evidence_detail` in JSON

## New Summary Metrics
- `invoice_evidence_pass`
- `invoice_evidence_fail`
- `invoice_evidence_unknown`
- `invoice_evidence_na`
- `phone_evidence_pass`
- `phone_evidence_fail`
- `phone_evidence_unknown`
- `phone_evidence_na`
- `prevented_second_pass_by_invoice_phone_evidence`

## New Reason Codes
- `INVOICE_EVIDENCE_CONFLICT`
- `INVOICE_EVIDENCE_UNKNOWN`
- `INVOICE_OWNER_MISMATCH`
- `INVOICE_CHANGED_AFTER_RUN7_ATTRIBUTION_UNKNOWN`
- `PHONE_EVIDENCE_CONFLICT`
- `PHONE_EVIDENCE_UNKNOWN`
- `PHONE_OWNER_CONFLICT`
- `PHONE_CHANGED_AFTER_RUN7_ATTRIBUTION_UNKNOWN`
- `MANUAL_PHONE_EVIDENCE_CONFLICT`

## Hash Update
The deterministic reconciliation SHA-256 now includes:

- `invoice_evidence_state`
- `invoice_evidence_consistent`
- `invoice_evidence_reason_codes`
- `phone_evidence_state`
- `phone_evidence_consistent`
- `phone_evidence_reason_codes`

The hash still orders rows by `current_internal_id ASC` and excludes `generated_at`.

## Read-only and Called-helper Analysis
The v0.8.54.1 reconciliation execution path calls only local helpers in `Biawin_CRM_Post_Run7_Reconciliation`.

Those helpers perform:

- `get_results`
- `get_row`
- `get_var`
- `prepare`
- table/column existence checks through `SHOW TABLES` and `SHOW COLUMNS`
- JSON/file reads for bundled Frozen Plan and blacklist
- array normalization, classification, CSV/JSON serialization, and hashing

They do not call:

- `Biawin_CRM_Customer_Service`
- `Biawin_CRM_Production_Readiness::sync_guard`
- final commit engine commit/apply helpers
- reconstruction commit helpers
- recalculate helpers
- repair helpers

No CRM domain mutations were introduced.

## Regression Coverage
The patch preserves:

- live Guard scope
- original MANUAL_REVIEW protection
- Run #7 quarantine correlation
- active assignment revalidation
- target missing handling
- human activity protection
- manual merge protection
- Guard invariant checks
- one primary classification per Guard row
- deterministic reconciliation SHA-256
- CSV/JSON export architecture
- absence of Run #8

## Test Notes
Static and syntax checks were run in the development environment. A pure-method regression test was run against the evidence evaluators and classifier:

- zero invoices where invoice evidence is not required -> `NOT_APPLICABLE`
- consistent invoice ownership -> `PASS`
- incompatible invoice ownership -> `FAIL`
- invoice changed after Run #7 without attribution -> `UNKNOWN`
- consistent phone ownership -> `PASS`
- phone tied to incompatible invoice owner -> `FAIL`
- manual phone conflict -> `MANUAL_REVIEW_REQUIRED`
- phone changed after Run #7 without attribution -> `UNKNOWN`
- original `MANUAL_REVIEW` remains `MANUAL_REVIEW_REQUIRED`
- resolved assignment plus invoice evidence failure is not `SECOND_PASS_SAFE`
- resolved assignment plus phone evidence unknown is not `SECOND_PASS_SAFE`
- all evidence and other checks passing may be `SECOND_PASS_SAFE`

Result: `TOTAL_PASS=12 TOTAL_FAIL=0`.

This environment is not Production, so no Production counts or SECOND_PASS_SAFE totals are claimed.
