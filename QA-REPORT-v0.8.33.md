# QA Report - Biawin CRM Integration v0.8.33

Date: 2026-08-18

## Scope

- Invoice ownership correction timeout handling.
- Partial execution recovery.

## Checks

- Ownership execution processes up to 1000 correction rows per request.
- Processed invoice codes are removed from the saved job after each batch.
- Remaining rows stay available for the next batch.
- Result page shows moved count for the current batch and remaining count for the job.
- Continue button is shown while rows remain.
- Initial execution can still create a backup; continuation batches avoid repeated backup by default.

## Verification

- PHP syntax lint passed for all plugin PHP files using `C:\xampp\php\php.exe -l`.
- `node --check assets/admin.js` passed.
- Final ZIP package built and version-checked.
