# QA Report - Biawin CRM Integration v0.8.26

Date: 2026-08-18

## Scope

- Financially unapproved invoice cleanup upload flow.
- Preview-to-execute flow without re-uploading the Excel/CSV file.
- Selective execution by cleanup category.
- Version/package readiness.

## Checks

- Upload form posts in `preview` mode automatically when a file is selected.
- Preview result stores the parsed CustomerID list in a per-user transient token for one hour.
- Execute form posts only the selected categories plus the preview token.
- Single-invoice profiles are deleted only when the `single_delete` category is selected.
- Multi-invoice unapproved invoices are excluded from totals only when the `multi_exclude` category is selected.
- Profiles without any financially unapproved invoice can be selected for the final report, but no database mutation is performed for them.
- Execute report preserves uploaded row count and unique CustomerID count from the preview job.
- Backup-before-cleanup remains enabled by default on execution.

## Verification

- PHP syntax lint passed for all plugin PHP files using `C:\xampp\php\php.exe -l`.
- Final ZIP package built and version-checked.
