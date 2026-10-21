# QA Report - v0.8.24

Date: 2026-08-17

Version tested: `0.8.24`

## Excel Comparison

Compared:

- `خروجی_تشخیص_مشتریان_زنجیره_ای.xlsx`
- `خروجی_نهایی_مشتریان_اصلاح_شده.xlsx`
- `biawin-customers-20260817-101306.xlsx`

Results:

- Chain invoice rows: `108,420`
- Corrected invoice rows: `108,420`
- Unique invoice codes in both files: `98,148`
- Missing invoice codes in corrected file: `0`
- New invoice codes in corrected file: `0`
- Invoice amount changes: `0`
- Financial approval status changes: `0`
- Invoice CustomerID changes: `98,145`
- Chain unique CustomerIDs: `57,309`
- Corrected unique CustomerIDs: `69,369`
- Portal export CustomerIDs: `57,140`
- Corrected CustomerIDs not currently in portal export: `12,229`
- Portal CustomerIDs missing from corrected file: `0`
- Blank trailing rows in both invoice sheets: `10,273`, all empty/zero sample rows after the real invoice range.

## Risk Assessment

- The corrected file does not remove or add invoice codes, and does not change invoice amounts or financial approval values.
- The corrected file heavily changes invoice-to-CustomerID ownership, so it must be imported only after creating a portal backup.
- After corrected import, the unapproved-invoice cleanup file can be previewed, then executed after counts are reviewed.

## Code Verification

- Backup/restore page added for all Biawin CRM tables.
- Import and cleanup flows include checked-by-default backup options.
- Import batch sizes increased for large files.
- PHP syntax check passed for all plugin PHP files.
