# Biawin CRM Release B.2.3 Acceptance Tests

Version: v0.8.59.0

## Required Behavioral Tests

1. Newest active assignment is selected per customer.
2. Duplicate active assignment rows do not double count customer workload.
3. Guarded active assignments are excluded from ordinary workable workload.
4. Managers can see Guarded workload separately.
5. Normal workable count uses `NORMAL_CANONICAL`.
6. Unassigned count uses `NORMAL_CANONICAL` customers with no canonical active assignment.
7. Manager cards and active assignment lists share adapter semantics.
8. Manager scope is preserved.
9. Agent scope is preserved and ordinary agents cannot request another agent's workload.
10. Department/organization boundary remains delegated to the existing portal/capability scope.
11. Assignment dependency unavailable state fails closed.
12. `null`, `UNKNOWN`, `UNAVAILABLE`, and `BLOCKED` are not rendered as zero.
13. Raw SQL errors, stack traces, and filesystem paths are not surfaced in dashboard output.
14. Portal dashboard has no direct business assignment SQL.
15. Adapter workload paths perform no mutation.

## Regression Suites

- `tests/reporting-foundation-regression.php`
- `tests/reporting-consumer-adapter-b1.php`
- `tests/management-dashboard-b21.php`
- `tests/management-dashboard-hardening-v08571.php`
- `tests/management-reports-b22.php`
- `tests/workload-kartable-b23.php`

## Release Gate

All PHP lint, JavaScript syntax checks, and regression tests must pass both before packaging and after fresh extraction of the final ZIP.
