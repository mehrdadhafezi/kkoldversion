# Biawin CRM v0.8.58.0
# Release B.2.2 Acceptance Tests

## Required Tests

| Test | Purpose |
|---|---|
| tests/reporting-foundation-regression.php | Foundation SOT, Guard/blacklist, financial, assignment, case, validation, Run #7/#8 boundaries |
| tests/reporting-consumer-adapter-b1.php | Shared Reporting Consumer Adapter contract and fail-closed scope behavior |
| tests/management-dashboard-b21.php | Main dashboard B.2.1 migration remains intact |
| tests/management-dashboard-hardening-v08571.php | Dashboard hardening and safe failure rendering remains intact |
| tests/management-reports-b22.php | B.2.2 Management Reports page uses adapter/SOT and no direct page business SQL |

## Static Acceptance

- Management Reports page has no direct SELECT, INSERT, UPDATE, DELETE, transient cache, or option write logic.
- Page rendering calls adapter-backed wrapper methods only.
- Adapter methods are public, scope-guarded, and fail closed for ordinary agents.
- Financial report values use ACTUAL_PAID_LIVE invoice evidence.
- `customers.total_paid` is not used as truth in migrated B.2.2 adapter methods.

## Package Acceptance

- Build from v0.8.58.0 source only.
- Extract final ZIP to a fresh temp directory.
- Rerun all listed tests from the extracted package.
- Run all-PHP lint and all-JS syntax checks from the extracted package.
