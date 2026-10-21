# QA Report - Biawin CRM Integration v0.8.27

Date: 2026-08-18

## Scope

- Financially unapproved invoice cleanup execute UX.
- Corrective import mode for CustomerID-based profile repair.
- Version/package readiness.

## Checks

- Real cleanup execution now displays a success notice with selected categories.
- Real cleanup execution reports the number of database-changing rows: deleted single-invoice profiles plus multi-invoice exclusions.
- Cleanup job token is removed after execution to prevent accidental repeated runs from the same preview.
- Import page exposes a corrective mode checkbox.
- Corrective mode requires CustomerID mapping.
- Corrective mode uses only phone 1 for imported invoice/customer phone data and ignores phone 2 for linking/merging.
- Corrective mode keeps CustomerID as the primary profile identity.

## Verification

- PHP syntax lint passed for all plugin PHP files using `C:\xampp\php\php.exe -l`.
- `node --check assets/admin.js` passed.
- Final ZIP package built and version-checked.
