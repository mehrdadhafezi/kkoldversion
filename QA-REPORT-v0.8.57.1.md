# BIAWIN CRM
# QA Report - v0.8.57.1

Release: Management Dashboard Production Hardening  
Base: v0.8.57.0 failed release candidate  
Target: v0.8.57.1

## Scope

This QA covers the narrow B.2.1 production hardening patch for the main management dashboard KPI consumers only.

## Assertions

- Base SHA256 was verified before patching.
- Version header, constant, readme stable tag, and changelog were updated to `0.8.57.1`.
- Main dashboard KPI count remains exactly 12.
- Main dashboard uses Reporting Consumer Adapter/SOT paths for customer, financial, assignment, and case KPIs.
- Dashboard financial KPI uses `management_actual_paid_total()` and avoids full normal-canonical snapshot materialization.
- Untrusted dashboard adapter results render as `نامطمئن` with bounded Persian safe messages.
- Raw SQL, `$wpdb`, table, column, and filesystem diagnostics are not rendered to dashboard users.
- Direct code-only upgrade gates exist for `0.8.55.2`, `0.8.56.0`, `0.8.56.1`, and `0.8.57.0` to `0.8.57.1`.
- Production package excludes historical reconstruction/control JSON artifacts and keeps only the runtime blacklist TXT file under `data/`.
- No customer mutation, aggregate rebuild, Guard change, Run #7 change, or Run #8 creation is included.

## Tests

| Test | Purpose | Result |
|---|---|---|
| `tests/reporting-foundation-regression.php` | Foundation SOT, fail-closed semantics, upgrade gates | PASS |
| `tests/reporting-consumer-adapter-b1.php` | Adapter contracts, security scopes, no mutation | PASS |
| `tests/management-dashboard-b21.php` | Static dashboard migration checks and KPI count | PASS |
| `tests/management-dashboard-hardening-v08571.php` | Real dashboard render checks, safe error messages, agent fail-closed scope | PASS |

## Known Environment Note

Local PHP emits startup warnings for missing XML-related extensions (`xml`, `xmlreader`, `xmlwriter`). These warnings occur before the test scripts run and did not prevent the PHP-only release tests from executing.

## Final Package Requirements

The final release ZIP must be built only after successful lint/syntax checks, then extracted into a fresh directory and retested from extracted contents.
