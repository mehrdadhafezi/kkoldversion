# Biawin CRM v0.8.58.0
# Release B.2.2 — Management Reports SOT Migration Report

## Summary

B.2.2 migrates the Management Reports page summary consumers from legacy page-level SQL to Reporting Consumer Adapter/SOT reads.

## Files Changed

- biawin-crm-integration.php
- readme.txt
- CHANGELOG.md
- includes/class-biawin-crm-activator.php
- includes/class-biawin-crm-reporting-consumer-adapter.php
- admin/class-biawin-crm-admin.php
- tests/reporting-foundation-regression.php
- tests/reporting-consumer-adapter-b1.php
- tests/management-dashboard-b21.php
- tests/management-dashboard-hardening-v08571.php
- tests/management-reports-b22.php

## Migrated Consumers

8 consumers migrated:

- Report KPI cards
- Customer preview table
- Satisfaction summary
- Contact status summary
- Route summary
- Route detail
- Service case summary
- Source service summary

## Dependencies Removed From Migrated Surface

- Page-level direct customer count queries
- Page-level direct report summary SQL
- `customers.total_paid` as financial truth
- Direct active-assignment owner selection for current owner
- Unsafe zero rendering for untrusted/null report numbers

## Preserved Boundaries

- CSV/Excel exports unchanged.
- REST reporting unchanged.
- Expert performance unchanged.
- Workload/Kartable unchanged.
- Detailed case and finance workflow reports unchanged.
- Guard unchanged.
- Run #7 terminal state untouched.
- Run #8 not created.

## Local Validation

All required local tests pass. PHP CLI emits startup warnings for missing optional XML extensions in this environment; those warnings do not affect lint/test execution.
