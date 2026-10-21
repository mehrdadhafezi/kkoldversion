# Biawin CRM QA Report v0.8.59.0

Release: Workload and Kartable SOT Migration
Base: v0.8.58.0

## Scope

B.2.3 migrates workload, Kartable, active-assignment-list, unassigned, per-agent workload, and agent-dashboard assignment KPI consumers to Reporting Consumer Adapter / Canonical Assignment Adapter semantics.

## Test Matrix

| Suite | Expected |
|---|---|
| PHP lint | PASS |
| JavaScript syntax | PASS |
| Foundation regression | PASS |
| B1 consumer adapter regression | PASS |
| B2.1 management dashboard regression | PASS |
| v0.8.57.1 hardening regression | PASS |
| B2.2 management reports regression | PASS |
| B2.3 workload/Kartable regression | PASS |

## Safety Assertions

- `UNKNOWN != ZERO`.
- `UNAVAILABLE != ZERO`.
- `BLOCKED != ZERO`.
- Adapter failures are displayed as safe nonnumeric states.
- Raw SQL errors are not printed in dashboard UI.
- No production action is included.

## Release Requirements

Final ZIP must be built after local tests, extracted into a fresh directory, and retested from the extracted package before release handoff.
