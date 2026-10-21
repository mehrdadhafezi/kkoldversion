# QA Report - v0.8.23

Date: 2026-08-17

Version tested: `0.8.23`

## Scope

- Added CustomerID Excel/CSV upload page for financially unapproved invoice cleanup.
- Added invoice-level exclusion from customer totals for multi-invoice profiles.
- Updated customer total recalculation, reconciliation preview, Data Audit mismatch checks, and comprehensive export labels.

## Source File Check

- `customerID هایی که باید حذف شن.xlsx`
- Rows read: `48,331`
- Unique CustomerID values: `48,331`
- Header detected: `CustomerID`

## Safety Rules Verified By Code Review

- Default mode is preview only.
- Real execution requires the `اجرای واقعی انجام شود` checkbox.
- Single-invoice profiles are deleted only when the profile has no operational history in assignments, interactions, activities, service/financial/deficiency/refund/complaint/attachment/tag tables.
- Multi-invoice profiles keep the unapproved invoice record and mark it as excluded from totals instead of deleting it.
- Every real exclusion writes audit and activity history.

## Technical Verification

- All PHP files passed syntax check with XAMPP PHP.
- Version header and `BIAWIN_CRM_VERSION` updated to `0.8.23`.
- `readme.txt` stable tag updated to `0.8.23`.

## Notes

- Production use should start with preview mode. Execute only after the preview counts and sample rows match expectation.
