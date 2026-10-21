# QA Report v0.8.55.2

## 1 Package Identity

Release: Biawin CRM `0.8.55.2`

Package name: `biawin-crm-integration-v0.8.55.2-production-validation-compatibility.zip`

Final SHA256 is reported externally after the ZIP is built.

## 2 Production Errors Addressed

Production v0.8.55.1 exposed two validation query failures:

- Financial Freshness: MariaDB rejected the query around the `dec` alias join.
- Event Double Count: Production `wp_bcrm_activities` has no `type` column.

Both failures were incorrectly rendered as trusted zero metrics in v0.8.55.1. v0.8.55.2 makes those validation failures explicit and fail-closed.

## 3 Activities Schema Evidence

The plugin schema defines:

- `wp_bcrm_activities.activity_type`
- no `wp_bcrm_activities.type`

`Biawin_CRM_Workflow_Service::activity()` writes event names to `activity_type`. Therefore the event double-count compatibility query now resolves and uses `activity_type`.

## 4 Financial MariaDB Compatibility

The Financial Freshness query now:

- avoids the `dec` alias
- uses `decl` as the latest-declared join alias
- uses `AS` aliases in subqueries
- uses `SUM(CASE WHEN ... THEN 1 ELSE 0 END)` aggregates instead of relying on boolean SUM parsing

Financial formulas are unchanged.

## 5 Financial Fail-Closed Behavior

If Financial Freshness query execution fails, the result is:

- `status=QUERY_FAILED`
- `trusted=false`
- numeric validation metrics are `null`
- `error` is sanitized

A successful zero-customer query remains `trusted=true` with zero counts.

## 6 Event-Risk Compatibility

The event-risk query now uses a read-only column compatibility resolver:

- prefers `activity_type`
- falls back only if a supported legacy `type` column actually exists
- returns unavailable state when no compatible event column exists

No schema mutation is performed.

## 7 Event-Risk Fail-Closed Behavior

If the event-risk query cannot execute, the result is:

- `status=QUERY_FAILED`
- `trusted=false`
- `potential_duplicate_assignment_return_events=null`

A successful query returning zero remains trusted zero.

## 8 Health Semantics

v0.8.55.2 separates:

- `core_source_of_truth_status`
- `validation_execution_status`
- `overall_reporting_sot_status`

Canonical population failure remains `BLOCKED`.

Financial/event query failure with healthy canonical population becomes `DEGRADED`, not `HEALTHY`.

## 9 UI Behavior

The Reporting SOT page now displays:

- `CORE SOURCE-OF-TRUTH: HEALTHY/BLOCKED`
- `VALIDATION EXECUTION: HEALTHY/DEGRADED/BLOCKED`

Blocked/null metrics render as `UNAVAILABLE`, not zero.

## 10 Run #7 Terminal Detector

Run #7 detector remains read-only. It continues to report `UNKNOWN` when the expected state row/table cannot prove terminal status. v0.8.55.2 does not manufacture a terminal PASS.

## 11 Deficiency Orphan Diagnostic

The existing orphan count remains read-only. v0.8.55.2 adds a read-only diagnostic helper for deficiency orphan rows with:

- deficiency case ID
- customer ID
- case status
- created time
- updated time

No repair, delete, reassignment, or migration is performed.

## 12 Regression Tests

Expanded regression result:

- `TOTAL_PASS=103`
- `TOTAL_FAIL=0`

New coverage includes:

- MariaDB-safe financial query construction
- financial success
- financial query failure
- null-not-zero financial failure metrics
- trusted zero-customer success
- activity event column resolver
- missing activity event column
- event-risk success zero
- event-risk success nonzero
- event-risk query failure
- validation HEALTHY/DEGRADED/BLOCKED states
- stale database error clearing before read-only table availability checks
- null metric rendering as `UNAVAILABLE`
- no mutation assertions

## 13 Syntax

Source pre-package QA:

- PHP files: 29
- PHP lint failures: 0
- JS files: 3
- JS syntax failures: 0

Final ZIP extraction QA must be repeated against the packaged archive before installation.

## 14 Safety Assertions

Schema Mutation = NO

Domain Mutation = NO

Guard Mutation = NO

Customer Mutation = NO

CustomerID Mutation = NO

Invoice Mutation = NO

Assignment Mutation = NO

Case Mutation = NO

Run #7 Mutation = NO

Run #7 Continuation = NO

Run #8 Implemented = NO

Run #8 Executable = NO

Frozen Plan Live Dependency = NO

## 15 Known Limitations

- Run #7 terminal remains `UNKNOWN` unless the existing live state table contains the expected terminal evidence.
- v0.8.55.2 does not repair the 59 production deficiency orphan rows.
- Release B, dashboard migration, export migration, indexes, and Run #8 remain out of scope.

## 16 Production Upgrade Guidance

The intended production upgrade is:

`0.8.55.1 -> 0.8.55.2`

The upgrade path is code-only/no-schema for approved recent reporting releases.

Install only after independent release audit passes.
