# Biawin CRM QA Report v0.8.59.1

Release: B.2.3 Workload/Kartable Production Hardening

## Test Matrix

| Suite | Result |
|---|---|
| PHP lint | PASS |
| JavaScript syntax | PASS |
| Foundation regression | PASS |
| B1 consumer adapter regression | PASS |
| B2.1 management dashboard regression | PASS |
| v0.8.57.1 hardening regression | PASS |
| B2.2 management reports regression | PASS |
| B2.3 workload/Kartable regression | PASS |
| B2.3 v0.8.59.1 hardening regression | PASS |

## Behavioral Coverage

- Filter-current workable and unassigned SOT paths.
- No raw assignment SQL truth in migrated rendering paths.
- Manager, Guarded, agent, and unassigned card/list reconciliation.
- Pagination preserving `total_count`.
- Manager scope proof and tamper denial.
- Agent self/cross-agent/global denial.
- Trusted and untrusted Guarded rendering.
- Strict read-path fail-closed behavior.
- Duplicate active row collapse and newest-active ownership.
- Guard and blacklist dependency fail-closed behavior.
- B2.1/B2.3 shared semantics preservation.

## Local Environment Note

The local PHP CLI emits startup warnings for optional XML extensions, but all PHP lint and regression tests pass.
