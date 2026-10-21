# QA Report - Biawin CRM Integration v0.8.29

Date: 2026-08-18

## Scope

- Corrective import identity behavior.
- Version/package readiness.

## Checks

- Corrective import page explains that CustomerID is the only ownership key.
- Correction mode still requires CustomerID mapping.
- Phone 1 and phone 2 are parsed and saved when present.
- In correction mode, phone values are not used for fallback customer lookup or merge decisions.
- Existing invoices are reassigned according to CustomerID when the corrected file is committed.

## Verification

- PHP syntax lint passed for all plugin PHP files using `C:\xampp\php\php.exe -l`.
- `node --check assets/admin.js` passed.
- Final ZIP package built and version-checked.
