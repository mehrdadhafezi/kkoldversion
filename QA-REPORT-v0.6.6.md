# QA Report — v0.6.6

## Source file inspected
`پورتال مشترکین.xlsx`

Observed worksheet facts:
- Data rows excluding header: 24,055
- Non-empty CustomerID cells: 24,055
- Distinct non-empty CustomerID values: 11,426
- Maximum numeric CustomerID: 11,803

Therefore `11803` is the maximum CustomerID value in this file, not the count of distinct CustomerID values. The CustomerID-first importer should target 11,426 source identities from this file before considering pre-existing/manual profiles.

## Static validation
- PHP syntax check: PASS for all PHP files.
- JavaScript syntax check: PASS for frontend/admin assets.
- Plugin version: 0.6.6.

## Business-rule checks
- CustomerID mapping exists and auto-detects `CustomerID`: PASS.
- CustomerID is used before phone matching during commit: PASS.
- Import preview exposes distinct CustomerID count: PASS.
- Old phone-grouped source profile collapses when invoices are moved away and it reaches zero invoices: PASS by code path.
- Manual invoice+phone attachment collapses empty source profile: PASS by code path.
- Negative remaining service credit allowed: PASS.
- Service selection no longer blocks over-allocation: PASS.
- Fast AJAX save sends explicit profile customer ID in URL params and body: PASS.
- Journey/service flows read profile-level ID: PASS.
- Service units, unit supervisors, members and unit-scoped assignment implemented: PASS.
- Phone imported as unverified and explicit verification implemented: PASS.
- Primary phone state independent from verification state: PASS.

## Runtime limitation
A full WordPress + MySQL browser E2E run cannot be executed in this build environment. Install on staging/local, allow the v0.6.6 migration to run, then re-import the same Excel file to normalize existing phone-based profiles into CustomerID-based profiles. Keep a database backup before upgrade/re-import.
