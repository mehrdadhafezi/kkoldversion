# QA Report v0.8.54

## Version
- Exact plugin version: `0.8.54`
- Feature name: Post-Run-7 Remaining Blacklist Read-only Reconciliation
- Mode: `READ_ONLY`
- Source Run UUID: `39c9c292-31e1-43df-8305-201bdec445ca`
- Source Frozen Plan SHA256: `bb87ff58b41a619203aa805bc9e557c887decfdea8888290f7906ebcf9191048`

## Files Changed
- `biawin-crm-integration.php`
- `includes/class-biawin-crm-historical-audit.php`
- `includes/class-biawin-crm-post-run7-reconciliation.php`
- `CHANGELOG.md`
- `QA-REPORT-v0.8.54.md`

## Architecture
v0.8.54 is implemented as a new additive class: `Biawin_CRM_Post_Run7_Reconciliation`.

It is wired through `plugins_loaded`, registers only three admin-post handlers, and renders one admin section on the existing historical audit page:

- `bcrm_v0854_run_reconciliation`
- `bcrm_v0854_export_reconciliation_csv`
- `bcrm_v0854_export_reconciliation_json`

The audit scope is derived only from live rows in `bcrm_readiness_quarantine`. It does not reuse the full v0.8.53.3.x Frozen Preflight as the remaining problem scope.

## Source Tables Read
- `bcrm_readiness_quarantine`
- `bcrm_customers`
- `bcrm_reconstruction_runs`
- `bcrm_activities`
- `bcrm_assignments`
- `bcrm_interactions`
- `bcrm_complaints`
- `bcrm_financial_cases`
- `bcrm_deficiency_cases`
- `bcrm_service_cases`
- `bcrm_customer_tags`
- `bcrm_invoices`
- `bcrm_phones`
- `bcrm_merge_events`
- `bcrm_audit_log`

Bundled read-only files:

- `data/blacklisted-customer-ids.txt`
- `data/frozen-canonical-plan-v0.8.53.2.json`

## Proof of Read-only Behavior
The v0.8.54 class contains no CRM domain mutation calls:

- no `$wpdb->insert`
- no `$wpdb->update`
- no `$wpdb->delete`
- no `INSERT`, `UPDATE`, `DELETE`, or `REPLACE` SQL against CRM domain tables
- no `START TRANSACTION`
- no `SELECT ... FOR UPDATE`
- no `dbDelta`
- no repair/recalculate/sync/commit helper calls

The only storage used by the run action is a WordPress transient for the current admin user's generated report. CSV and JSON exports recompute the live read-only report directly.

## Classification Rules
Every current Guard row receives exactly one of:

- `SECOND_PASS_SAFE`
- `ACTIVE_ASSIGNMENT_STILL_CONFLICTED`
- `TARGET_RECONSTRUCTION_REQUIRED`
- `MANUAL_REVIEW_REQUIRED`
- `UNEXPECTED_BLOCK`

`SECOND_PASS_SAFE` requires intact Guard/customer/blacklist invariants, a non-manual frozen plan row, known Run #7 quarantine evidence, valid non-blacklisted final CustomerID, no final-ID collision, valid target state, no active assignment, no manual merge evidence, no conflicting human activity after Run #7, and consistent identity evidence.

## Block Reason Codes
Implemented machine-readable reason codes include:

- `GUARD_WITHOUT_CUSTOMER`
- `GUARD_CUSTOMER_NOT_BLACKLISTED`
- `GUARD_CUSTOMER_ID_MISMATCH`
- `GUARD_WITHOUT_BLACKLISTED_CUSTOMER`
- `FROZEN_PLAN_ROW_MISSING`
- `RUN7_QUARANTINE_NOT_FOUND`
- `UNKNOWN_RUN7_QUARANTINE_REASON`
- `ORIGINAL_MANUAL_REVIEW_PROTECTED`
- `EXPECTED_FINAL_CUSTOMER_ID_MISSING`
- `EXPECTED_FINAL_CUSTOMER_ID_BLACKLISTED`
- `FINAL_CUSTOMER_ID_COLLISION`
- `TARGET_CUSTOMER_ID_BLACKLISTED`
- `MISSING_TARGET_NOW_EXISTS`
- `TARGET_ROW_MISSING`
- `TARGET_INTERNAL_ID_MISSING`
- `TARGET_CUSTOMER_ID_CHANGED`
- `TARGET_CUSTOMER_ID_COLLISION`
- `TARGET_IDENTITY_MISMATCH`
- `ACTIVE_ASSIGNMENT_PRESENT`
- `HUMAN_ACTIVITY_AFTER_RUN7`
- `HUMAN_ACTIVITY_ATTRIBUTION_UNKNOWN`
- `MANUAL_MERGE_EVIDENCE`
- `MANUAL_MERGE_CONFLICT`
- `IDENTITY_EVIDENCE_INSUFFICIENT`

## Run #7 Correlation Logic
The report reads `bcrm_reconstruction_runs` by the exact Run #7 UUID, or by the existing final commit mode and frozen plan hash. It decodes `result_json.quarantine_details` and indexes quarantine evidence by source/current internal ID.

Known Run #7 quarantine reasons allowed for normal reconciliation are:

- `ACTIVE_ASSIGNMENT_CONFLICT`
- `TARGET_ROW_MISSING`

Any other raw quarantine reason is preserved and classified as `UNEXPECTED_BLOCK`.

## MANUAL_REVIEW Protection
Frozen Plan rows whose `canonical_decision` is `MANUAL_REVIEW` are never classified as `SECOND_PASS_SAFE`. They remain `MANUAL_REVIEW_REQUIRED` unless a stronger invariant failure makes them `UNEXPECTED_BLOCK`.

## Human Activity Detection
The audit uses the persisted Run #7 `started_at` timestamp when available, falling back to `2026-08-31 01:13:36`.

It inspects actor/time evidence across activities, interactions, assignments, complaints, financial cases, deficiency cases, service cases, customer tags, and customer audit logs. Migration/guard/reconstruction/re-key action types are filtered out. Explicit nonzero human actors after Run #7 produce `HUMAN_ACTIVITY_AFTER_RUN7`; post-cutoff rows without reliable attribution produce `HUMAN_ACTIVITY_ATTRIBUTION_UNKNOWN` and fail closed.

## Target Missing Handling
Rows with Run #7 `TARGET_ROW_MISSING`, live missing target rows, or `CREATE_MISSING_VALID_TARGET` plan mode are classified as `TARGET_RECONSTRUCTION_REQUIRED` when no stronger invariant/manual-review block is present. v0.8.54 does not create or reconstruct any target.

## Guard Invariants
The report summarizes:

- Remaining Guarded
- Current Blacklisted
- Blacklisted Without Guard
- Guard Without Blacklisted Customer
- Guard CustomerID Mismatch

Guard anomalies are never silently ignored.

## Report Hash Canonicalization
The reconciliation hash orders rows by `current_internal_id ASC`, selects stable scalar fields, sorts array values, joins fields as `key=value` lines, and calculates SHA-256 over the UTF-8 canonical string. Pretty-printed JSON formatting is not used as the semantic hash source.

## Performance Strategy
The scope is read from Guard once. Current blacklisted customers, targets, domain counts, active assignments, human activity evidence, and merge evidence are batch-loaded in chunks instead of performing per-row full scans.

## Verification
- PHP lint: see completion response.
- JavaScript syntax: see completion response.
- Mutation search: see completion response.

## Known Limitations
- This development environment was not connected to Production, so no production classification counts are claimed here.
- Human activity attribution is intentionally conservative when a table contains post-Run-7 evidence without a reliable actor column/value.
- Identity consistency uses current/frozen display-name agreement where available; ambiguous identity evidence fails closed.
