# QA Report - v0.8.25

Date: 2026-08-18

Version tested: `0.8.25`

## Issue Reproduced From Report

- Cleanup upload crashed when `display_name` was `NULL`.
- LocalWP/Windows showed warnings while recursively removing temporary XLSX extraction folders under `C:\Windows\TEMP`.

## Fixes

- Cleanup sample rows now accept null customer names/invoice codes and render safe fallbacks.
- XLSX extraction temporary folders are created under `wp-content/uploads/biawin-crm-temp` when possible.
- Recursive temp cleanup suppresses and tolerates inaccessible/missing directories.

## Verification

- All plugin PHP files passed syntax check.
