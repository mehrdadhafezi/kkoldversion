# Audit Blocker Closure v0.8.59.1

## Blocker 1: Filter-current Kartable KPIs bypass canonical SOT

- Root Cause: Assignment page calculated filter-current assigned/unassigned cards with raw `EXISTS` and assignment-row arithmetic.
- Fix: Added `management_assignment_filter_summary()` and routed filter-current cards through Reporting Consumer Adapter / Canonical Assignment SOT.
- Behavioral Test: `workload-kartable-hardening-v08591.php` tests 1, 2, 3, and 20.
- Result: PASS.

## Blocker 2: Filter-current unassigned semantics

- Root Cause: Raw unassigned used any active assignment row and did not prove NORMAL_CANONICAL exclusion at the shared semantic boundary.
- Fix: Adapter summary defines unassigned as filtered NORMAL_CANONICAL customers without canonical newest-active assignment.
- Behavioral Test: `workload-kartable-hardening-v08591.php` tests 2, 7, 21, 22, 23, 24, and 25.
- Result: PASS.

## Blocker 3: Card/list reconciliation incomplete

- Root Cause: List methods returned paginated row count as value and no separate authoritative total.
- Fix: Added `total_count` and `returned_count` contracts to management, Guarded, agent, and filter-current row methods.
- Behavioral Test: `workload-kartable-hardening-v08591.php` tests 4, 5, 6, 7, and 8.
- Result: PASS.

## Blocker 4: Manager department/organization cross-scope denial not certifiable

- Root Cause: Manager aggregate methods accepted role-only scope.
- Fix: B.2.3 workload aggregate methods require `scope_proven` and `requesting_user_id`; unsupported or tampered requested department/organization keys fail closed.
- Behavioral Test: `workload-kartable-hardening-v08591.php` tests 9, 10, 11, and 12.
- Result: PASS.

## Blocker 5: Guarded workload trust rendering

- Root Cause: Per-agent Guarded column passed `trusted=false` even when the adapter returned trusted numeric data.
- Fix: Rendering now passes the adapter trust flag for `guarded_customers`; default unavailable state remains bounded unknown.
- Behavioral Test: `workload-kartable-hardening-v08591.php` tests 17, 18, and 19.
- Result: PASS.

## Blocker 6: Strict read-path behavior

- Root Cause: v0.8.59.0 did not expose all strict read paths at shared semantic boundaries and did not prove fail-closed total/list contracts.
- Fix: Added strict read adapter methods for filter-current summary/rows, Guarded rows, and agent rows; all return structured `trusted=false/value=null` on dependency failure and contain no write fallback.
- Behavioral Test: `workload-kartable-hardening-v08591.php` tests 20 and final write-fallback assertion.
- Result: PASS.
