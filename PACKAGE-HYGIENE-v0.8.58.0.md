# Package Hygiene — Biawin CRM v0.8.58.0

## Intended Package

biawin-crm-integration-v0.8.58.0-management-reports-sot-migration.zip

## Hygiene Requirements

- No `.git` directory.
- No database dumps.
- No NDJSON backups.
- No credentials or secrets.
- No accidental workspace output directories.
- No generated production action artifacts.
- No Run #8 artifacts.

## Verification Plan

The final package must be extracted into a fresh directory and checked with:

- SHA256 hash calculation.
- Top-level package structure inspection.
- All required regression tests.
- All-PHP lint.
- All-JS syntax checks.
- Forbidden artifact scan.

## Notes

The bundled `data/blacklisted-customer-ids.txt` is an intentional plugin input file and is not a backup/export artifact.
