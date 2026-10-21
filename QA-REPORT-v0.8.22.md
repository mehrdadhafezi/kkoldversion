# QA Report - v0.8.22

Date: 2026-08-16
Environment: Local WordPress QA site (`bcrm_qa_0812`)
Plugin: `biawin-crm-integration`
Version tested: `0.8.22`

## Scope

Read-only live data audit tooling for validating KPI cards, assignment state, contact status, and integrity checks on the production database.

## Added

- Admin submenu: `CRM بیاوین > Data Audit`.
- Portal view: `Data Audit`.
- JSON export action: `bcrm_data_audit_json`.
- Read-only report sections:
  - Table existence and row counts.
  - KPI recalculation.
  - Assignment scenarios.
  - Contact status counts.
  - Assignment by agent.
  - Integrity summaries.
  - Sample rows for suspicious records.

## QA Results

### Admin Data Audit Page

Result:
- Page rendered successfully.
- JSON download link rendered.
- Assignment scenarios rendered.
- Integrity summaries rendered.
- Report metadata confirms `read_only=true`.

Status: PASS

### Audit Report Engine

Result:
- Report generated with these top-level keys:
  - `meta`
  - `tables`
  - `kpis`
  - `assignment_scenarios`
  - `contact_status_counts`
  - `assignment_by_agent`
  - `integrity_summary`
  - `samples`

Status: PASS

### Portal Data Audit View

Result:
- Portal route `bcrm_view=data-audit` rendered.
- JSON download link rendered.
- Read-only notice rendered.

Status: PASS

### Syntax Check

Result:
- All PHP files passed syntax check.

Status: PASS

## Notes

An initial QA run found one MySQL compatibility issue around using an aggregate alias in `HAVING`/`ORDER BY`. It was fixed and the audit page was rerun successfully.
