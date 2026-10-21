# Biawin CRM Release B.2.3 Migration Report

Version: v0.8.59.0
Release Name: Workload and Kartable SOT Migration
Base: v0.8.58.0

## Files Changed

- `biawin-crm-integration.php`
- `readme.txt`
- `CHANGELOG.md`
- `includes/class-biawin-crm-activator.php`
- `includes/class-biawin-crm-reporting-consumer-adapter.php`
- `includes/class-biawin-crm-frontend.php`
- `admin/class-biawin-crm-admin.php`
- `tests/reporting-foundation-regression.php`
- `tests/reporting-consumer-adapter-b1.php`
- `tests/management-dashboard-b21.php`
- `tests/management-dashboard-hardening-v08571.php`
- `tests/management-reports-b22.php`
- `tests/workload-kartable-b23.php`
- `RELEASE-B2.3-WORKLOAD-INVENTORY.md`
- `RELEASE-B2.3-MIGRATION-CONTRACT.md`
- `RELEASE-B2.3-ACCEPTANCE-TESTS.md`
- `RELEASE-B2.3-MIGRATION-REPORT.md`
- `QA-REPORT-v0.8.59.0.md`
- `PACKAGE-HYGIENE-v0.8.59.0.md`

## Consumers Migrated

- Admin assignment/Kartable KPI cards.
- Admin per-agent workload table.
- Admin active assignment list.
- Portal manager unassigned workload card.
- Portal agent assignment-related KPI cards.
- B.2.1 management workload equivalents reconciled to the same adapter contract.

## Old Dependencies Removed From Migrated Surfaces

- Raw active assignment count as business workload truth.
- Portal dashboard direct assignment table dependency.
- Transient-cached dashboard assignment truth.
- Duplicate active assignment row counting as customer workload.

## New Adapter Methods

- `management_workload_cards()`
- `management_agent_workload_rows()`
- `management_recent_assignment_rows()`
- `agent_dashboard_assignment_summary()`

## Semantics

- Workable active workload is one newest active assignment per `NORMAL_CANONICAL` customer.
- Guarded workload is visible separately and excluded from ordinary expert workload.
- Unassigned workload is `NORMAL_CANONICAL` with no canonical active assignment.
- Unknown or blocked adapter values render as safe nonnumeric state.

## Safety

- No schema mutation.
- No domain mutation.
- No Guard mutation.
- No assignment mutation.
- No Run #7 continuation.
- No Run #8 implementation.
