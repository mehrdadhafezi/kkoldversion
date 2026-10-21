# QA Report - Biawin CRM Integration v0.8.32

Date: 2026-08-18

## Scope

- CRM backup upload/restore error handling.
- Large backup restore on local test sites.

## Checks

- Upload restore now checks PHP upload error codes before checking `tmp_name`.
- Oversized backup uploads show the configured PHP upload limit and instruct the operator to use the local/server path field.
- Backup restore form allows either browser upload or a readable local/server file path.
- Local/server file path restore copies the backup into the CRM backup directory before restore.
- File extension validation remains restricted to NDJSON/JSON backup files.

## Verification

- PHP syntax lint passed for all plugin PHP files using `C:\xampp\php\php.exe -l`.
- `node --check assets/admin.js` passed.
- Final ZIP package built and version-checked.
