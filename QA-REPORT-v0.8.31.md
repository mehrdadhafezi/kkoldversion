# QA Report - Biawin CRM Integration v0.8.31

Date: 2026-08-18

## Scope

- Backup upload/restore for local testing.
- Invoice ownership correction behavior when target CustomerID does not exist.

## Checks

- Backup page now includes an upload-and-restore form for NDJSON backup files.
- Uploaded backup files are stored in the CRM backup directory before restore.
- Optional pre-restore backup remains enabled by default.
- Invoice ownership preview marks missing target CustomerIDs as profiles that will be created during execution.
- Invoice ownership execution creates missing target Customer profiles with the requested CustomerID.
- Execution only updates invoice ownership and same-invoice service ownership, then recalculates affected customer totals.
- Existing profiles, phones, interactions, assignments, complaints, refunds, and history are not automatically deleted or moved.

## Verification

- PHP syntax lint passed for all plugin PHP files using `C:\xampp\php\php.exe -l`.
- `node --check assets/admin.js` passed.
- Final ZIP package built and version-checked.
