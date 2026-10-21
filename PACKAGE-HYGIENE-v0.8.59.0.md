# Biawin CRM Package Hygiene v0.8.59.0

Release: Workload and Kartable SOT Migration

## Package Rules

The release package must not contain:

- Historical customer data.
- Reconstruction data.
- Backups.
- SQL dumps.
- Exports.
- Secrets.
- Debug files.
- `.git` metadata.
- Workspace temp files.

The runtime-required official blacklist deny-list may remain packaged as:

`data/blacklisted-customer-ids.txt`

## Expected Artifact

`biawin-crm-integration-v0.8.59.0-workload-kartable-sot-migration.zip`

## Verification

The final ZIP must be freshly extracted and retested. Package hygiene scan must be run on extracted contents before final handoff.
