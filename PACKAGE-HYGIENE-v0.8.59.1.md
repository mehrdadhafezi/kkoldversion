# Biawin CRM Package Hygiene v0.8.59.1

Release: B.2.3 Workload/Kartable Production Hardening

## Required Exclusions

The final package must not contain:

- Historical customer data.
- Reconstruction artifacts.
- Backups.
- SQL dumps.
- Production exports.
- Credentials.
- Private keys.
- Debug dumps.
- `.git` metadata.
- Workspace temp files.

## Allowed Runtime File

`data/blacklisted-customer-ids.txt` remains allowed only as the runtime-required official deny-list.

## Verification

The final ZIP must be freshly extracted and all lint, syntax, regression, hardening, and hygiene checks must pass against the extracted package.
