# Package Hygiene v0.8.59.6

## Target Package

`biawin-crm-integration-v0.8.59.6-receipt-buttons-fix.zip`

## Expected Package Rules

- Include plugin source files and release QA documents.
- Exclude `.git`.
- Exclude generated output folders.
- Do not include database dumps, backups, credentials, or temporary files.
- Build only after lint, JavaScript syntax checks, and tests pass.
