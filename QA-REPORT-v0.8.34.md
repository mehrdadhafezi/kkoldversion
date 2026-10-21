# QA Report - Biawin CRM Integration v0.8.34

Date: 2026-08-18

## Scope

- Financially unapproved invoice cleanup timeout handling.
- Partial cleanup recovery.

## Checks

- Cleanup execution processes up to 1000 CustomerIDs per request.
- Processed CustomerIDs are removed from the saved cleanup job after each batch.
- Remaining CustomerIDs stay available for the next batch.
- Result page shows changed count for the current batch and remaining count for the job.
- Continue button is shown while rows remain.
- Continuation batches reuse the selected cleanup actions.

## Verification

- PHP syntax lint passed for all plugin PHP files using `C:\xampp\php\php.exe -l`.
- `node --check assets/admin.js` passed.
- Final ZIP package built and version-checked.
