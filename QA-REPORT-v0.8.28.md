# QA Report - Biawin CRM Integration v0.8.28

Date: 2026-08-18

## Scope

- Financially unapproved invoice cleanup no-op handling.
- Corrective import discoverability and default behavior.

## Checks

- Added admin submenu `ایمپورت اصلاحی`.
- Corrective import page uses the standard batch import pipeline with correction mode checked by default.
- Correction mode requires CustomerID and ignores phone 2 for linking/merging.
- Cleanup execution rejects submissions that only include the informational no-op category.
- The no-op cleanup category is displayed as informational and disabled in the execute form.

## Verification

- PHP syntax lint passed for all plugin PHP files using `C:\xampp\php\php.exe -l`.
- `node --check assets/admin.js` passed.
- Final ZIP package built and version-checked.
