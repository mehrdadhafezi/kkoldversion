# QA Report v0.8.55.1

## 1 Package Identity

Release: Biawin CRM `0.8.55.1`

Package name: `biawin-crm-integration-v0.8.55.1-reporting-fail-closed-hardening.zip`

Final SHA256 is reported externally after the ZIP is built, because embedding the final archive hash inside the archive would change the archive hash.

## 2 Base / Upgrade Path

Base production version: `0.8.54.2`

Direct upgrade supported: `0.8.54.2 -> 0.8.55.1`

Accidental failed-candidate upgrade supported: `0.8.55.0 -> 0.8.55.1`

Both intended paths are code-only and do not run schema creation, customer recalculation, Guard sync, migration, Run #7 continuation, or Run #8.

## 3 Exact Changes vs v0.8.55.0

- Version changed from `0.8.55.0` to `0.8.55.1`.
- Population service now exposes structured source status and trust metadata.
- Guard and blacklist dependency failures now block trusted canonical reporting.
- Assignment service now exposes `unassigned_normal_canonical_customers`.
- Validation service propagates source status and overall BLOCKED/HEALTHY state.
- Validation page shows source-of-truth BLOCKED/HEALTHY state and renders blocked counts as `UNAVAILABLE`.
- Regression harness expanded to fail-closed behavioral/adversarial coverage.

## 4 Blocking Findings Addressed

Blocker 1 Guard fail-open: FIXED

Blocker 2 Blacklist fail-open: FIXED

Blocker 3 canonical unassigned: FIXED

## 5 Population Dependency Contract

`UNKNOWN != ZERO`

Every population dependency reports:

- `available`
- `trusted`
- `status`
- `count`
- `error`
- source metadata

Unavailable sources are not converted into empty trusted ID sets.

## 6 Guard Fail-Closed Behavior

Guard states detected:

- `AVAILABLE`
- `CLASS_UNAVAILABLE`
- `TABLE_UNAVAILABLE`
- `QUERY_FAILED`

When Guard is unavailable, `NORMAL_CANONICAL` is `BLOCKED`, `trusted=false`, and `count=null`.

## 7 Blacklist Fail-Closed Behavior

Blacklist states detected:

- `AVAILABLE`
- `MISSING`
- `UNREADABLE`
- `LOAD_FAILED`
- `EMPTY_UNEXPECTED`
- `TABLE_UNAVAILABLE`
- `QUERY_FAILED`

When blacklist evidence is unavailable or unexpectedly empty, `BLACKLISTED_LIVE` and `NORMAL_CANONICAL` are not trusted.

## 8 NORMAL_CANONICAL Trust Gate

`NORMAL_CANONICAL` is trusted only when:

- `ALL_LIVE` is available
- Guard source is available
- Blacklist source is available

Otherwise `NORMAL_CANONICAL.count = null`.

## 9 Population Invariants

When all sources are trusted:

`NORMAL_CANONICAL = ALL_LIVE - union(GUARDED, BLACKLISTED_LIVE)`

The invariant avoids duplicate subtraction when a customer belongs to both Guarded and Blacklisted populations.

## 10 Guard / Blacklist Divergence

The service exposes:

- `guarded_not_blacklisted`
- `blacklisted_not_guarded`

Divergence is a data-quality indicator and does not mutate either source.

## 11 Assignment Canonical Helper

The assignment service now exposes:

`unassigned_normal_canonical_customers`

It depends on trusted `NORMAL_CANONICAL`. If canonical population is blocked, canonical unassigned reporting is also blocked and does not fall back to all-live.

## 12 Validation Service

The validation summary exposes:

- `guard_source_status`
- `blacklist_source_status`
- `canonical_population_status`
- `overall_reporting_sot_status`

Blocked dependency state is propagated instead of being reported as pass/zero.

## 13 Validation Page UX

The admin page shows:

- `REPORTING SOURCE-OF-TRUTH: HEALTHY` when dependencies are trusted
- `REPORTING SOURCE-OF-TRUTH: BLOCKED` when Guard/blacklist/canonical evidence is unavailable

Blocked/null metrics render as `UNAVAILABLE`, not zero.

## 14 Financial Semantics Regression

Financial formulas were not redesigned.

Actual paid remains based on invoice `amount_paid` with `exclude_from_total` honored. Declared-paid and discrepancy semantics remain consistent with v0.8.55.0.

## 15 Performance / Request Memoization

The validation run resets and uses request-scoped population memoization. Blacklist/Guard source resolution is reused within a validation request and is not persisted.

## 16 Activator Safety

The activator exact safe gate supports:

- `0.8.54.2 -> 0.8.55.1`
- `0.8.55.0 -> 0.8.55.1`

The safe gate only updates the plugin DB version option and returns.

## 17 Regression Tests

Expanded regression harness result:

- `TOTAL_PASS=76`
- `TOTAL_FAIL=0`

## 18 Adversarial Tests

The harness covers:

- Guard available non-empty
- Guard available valid zero
- Guard class unavailable
- Guard table unavailable
- Guard query failure
- Blacklist available non-empty
- Blacklist path missing
- Blacklist unreadable
- Blacklist load failure
- Blacklist unexpectedly empty
- both dependencies healthy
- one or both dependencies unavailable
- overlap/double-subtraction invariant
- Guard/blacklist divergence metadata
- canonical assignment blocking
- null/unavailable rendering
- Run #7 terminal metadata
- Run #8 absence

## 19 PHP / JS Syntax

Final ZIP extraction QA:

- PHP lint files: 29
- PHP lint failures: 0
- JS syntax files: 3
- JS syntax failures: 0

The local PHP CLI emits startup warnings when loaded with the host php.ini because XML-related extensions are missing from this machine. Final lint/test verification was run with `php -n` to avoid unrelated local extension startup noise.

## 20 Mutation Audit

RUN #7 MUTATION = NO

RUN #7 CONTINUATION = NO

RUN #8 IMPLEMENTED = NO

RUN #8 EXECUTABLE = NO

GUARD MUTATION = NO

GUARD SYNC = NO

CUSTOMER MIGRATION = NO

CUSTOMERID MUTATION = NO

CUSTOMER MUTATION = NO

INVOICE MUTATION = NO

PHONE MUTATION = NO

ASSIGNMENT MUTATION = NO

CASE MUTATION = NO

HISTORY MUTATION = NO

DATABASE DOMAIN MUTATION = NO

## 21 Schema Audit

No schema changes were introduced in v0.8.55.1.

No index changes were introduced in this patch.

DATABASE SCHEMA MUTATION = NO

## 22 Security / Secret Scan

Final secret scan found no private keys, API secrets, database dumps, or credential files in the v0.8.55.1 package.

Expected code-level mentions such as password form fields, nonces, job tokens, and upload/import tokens remain in existing application code and are not embedded production credentials.

## 23 Known Limitations

- Per-customer financial freshness diagnostics remain deferred to Release B.
- Invoice and interaction composite index recommendations remain deferred because this patch forbids schema mutation.
- This release does not claim production approval; it requires an independent release gate audit.

## 24 Production Upgrade Guidance

Install only after independent release audit passes.

The intended production base remains `0.8.54.2`; failed candidate `0.8.55.0` does not need to be installed first.

## 25 Next Release Boundary

Release B may add dashboard/export consumers, per-customer financial diagnostics, and approved performance indexes.

v0.8.55.1 does not implement Run #8, dashboard migration, domain migration, or schema migration.
