# Reporting Source-of-Truth v0.8.55.1

## Purpose

v0.8.55.1 hardens the Reporting Source-of-Truth Foundation so production reporting cannot confuse unavailable evidence with a valid zero.

This is a read-only foundation release. It does not implement dashboard migration, Release B, Run #8, customer migration, Guard sync, or schema mutation.

## Core Rule

`UNKNOWN != ZERO`

A dependency that cannot be loaded is not an empty population. It is unsafe for trusted canonical reporting.

## Population Contract

Population metrics expose:

- `key`
- `label`
- `count`
- `trusted`
- `status`
- `source_status`
- `description`
- `dependency_errors`

When a required source is unavailable, blocked counts are `null` and must render as `UNAVAILABLE`.

## Guard Source Status

Guard source states include:

- `AVAILABLE`
- `CLASS_UNAVAILABLE`
- `TABLE_UNAVAILABLE`
- `QUERY_FAILED`

Valid empty Guard rows are allowed only when the Guard source was successfully queried. Unavailable Guard evidence blocks `NORMAL_CANONICAL`.

## Blacklist Source Status

Blacklist source states include:

- `AVAILABLE`
- `MISSING`
- `UNREADABLE`
- `LOAD_FAILED`
- `EMPTY_UNEXPECTED`
- `TABLE_UNAVAILABLE`
- `QUERY_FAILED`

The official production blacklist is expected to be non-empty. An empty loaded blacklist is treated as `EMPTY_UNEXPECTED` unless a future release introduces an explicit authoritative empty-list contract.

## Canonical Trust Gate

Trusted `NORMAL_CANONICAL` requires:

- live customers available
- Guard source available
- blacklist source available

If any dependency is unavailable, `NORMAL_CANONICAL` becomes:

- `trusted=false`
- `status=BLOCKED`
- `count=null`

No best-effort canonical count is exposed as business truth.

## Invariant

When trusted:

`NORMAL_CANONICAL = ALL_LIVE - union(GUARDED, BLACKLISTED_LIVE)`

Using union semantics prevents double subtraction when the same customer is both Guarded and blacklisted.

## Guard / Blacklist Divergence

The service reports:

- `guarded_not_blacklisted`
- `blacklisted_not_guarded`

These are data-quality indicators only. The release does not mutate Guard rows or blacklist evidence.

## Assignment Semantics

Current assignment remains:

`newest active assignment per customer`

The assignment service reports all-live diagnostics separately from canonical metrics.

Canonical unassigned reporting is exposed as:

`unassigned_normal_canonical_customers`

It is trusted only when `NORMAL_CANONICAL` is trusted. It does not fall back to all-live.

## Validation Page

The page:

`admin.php?page=biawin-crm-reporting-sot`

shows:

- `REPORTING SOURCE-OF-TRUTH: HEALTHY` when all canonical dependencies are trusted
- `REPORTING SOURCE-OF-TRUTH: BLOCKED` when Guard, blacklist, or canonical population evidence is unavailable

Blocked metrics render as `UNAVAILABLE`.

## Read-Only Safety

RUN #7 MUTATION = NO

RUN #7 CONTINUATION = NO

RUN #8 IMPLEMENTED = NO

RUN #8 EXECUTABLE = NO

GUARD MUTATION = NO

GUARD SYNC = NO

CUSTOMER MIGRATION = NO

DATABASE DOMAIN MUTATION = NO

DATABASE SCHEMA MUTATION = NO

FROZEN PLAN LIVE BUSINESS DEPENDENCY = NO

## Release Boundary

Deferred to Release B:

- dashboard/export consumers
- per-customer financial freshness diagnostics
- production performance index changes
- any Run #8 planning or execution

v0.8.55.1 must still pass independent release audit before production installation approval.
