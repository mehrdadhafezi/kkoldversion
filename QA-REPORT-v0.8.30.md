# QA Report - Biawin CRM Integration v0.8.30

Date: 2026-08-18

## Scope

- Full CRM backup/export entry point for live-site testing.
- Invoice ownership correction workflow.
- Version/package readiness.

## Checks

- Added admin submenu `اصلاح مالکیت فاکتورها`.
- The page exposes a full CRM backup/export action using the existing restorable backup system.
- Correction preview accepts XLSX/CSV files with CustomerID and invoice-code columns.
- Preview reports found/missing invoices, found/missing target CustomerIDs, already-correct rows, and rows ready to move.
- Real execution requires explicit confirmation and creates a backup by default.
- Execution only updates `invoices.customer_id` and same-invoice `services.customer_id`.
- Execution recalculates affected source and target customers.
- Execution does not move interactions, assignments, phones, complaints, refunds, or delete/collapse profiles.

## Verification

- PHP syntax lint passed for all plugin PHP files using `C:\xampp\php\php.exe -l`.
- `node --check assets/admin.js` passed.
- Final ZIP package built and version-checked.
