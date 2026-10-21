# QA Report - Biawin CRM Integration v0.8.35

Date: 2026-08-18

## Scope

- Financially unapproved invoice cleanup upload/preview flow.
- Resumable preview batches for large CustomerID files.
- Resumable execution batches after preview completion.
- Version metadata and changelog.

## Changes Verified

- Removed automatic form submit after selecting the cleanup file.
- Initial cleanup preview processes at most 1000 CustomerIDs and stores the full job for later continuation.
- Preview continuation processes the next batch and keeps cumulative preview counts.
- Real cleanup execution remains batch-based and continues from the stored job without re-uploading the file.
- Execution controls are shown only after preview has finished, preventing incomplete category counts from being used.

## Tests

- `php -l admin/class-biawin-crm-admin.php`
  - Result: Passed. PHP emitted local startup warnings for missing XML extensions, but syntax validation completed with `No syntax errors detected`.

## Operational Notes

- For large cleanup files, click `بررسی فایل`, then click `ادامه محاسبه پیش‌نمایش` until the remaining CustomerID count reaches zero.
- After preview completion, select the real cleanup categories and run execution. Execution also continues in batches if needed.
- Existing database safety rules are unchanged: single-invoice unapproved profiles are deleted only when they have no operational history; multi-invoice profiles keep the invoice row and exclude only its amount from totals.
