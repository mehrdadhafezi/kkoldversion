# Biawin CRM v0.8.56.1
# QA Report - Reporting Consumer Adapter Hardening

## Base

- Base ZIP: `biawin-crm-integration-v0.8.56.0-reporting-consumer-adapter-foundation.zip`
- Base expected SHA: `AD9F6ADCE29FBEE6BF41CD354761F5D24F2213E97F3F3FB81F7989D439C4D3FC`
- Base actual SHA: `AD9F6ADCE29FBEE6BF41CD354761F5D24F2213E97F3F3FB81F7989D439C4D3FC`
- SHA match: PASS

## Version Consistency

- Plugin header: `0.8.56.1`
- `BIAWIN_CRM_VERSION`: `0.8.56.1`
- readme stable tag: `0.8.56.1`
- Changelog entry: present

## Fixes

- Public `NORMAL_PLUS_VISIBLE_QUARANTINE` IDs now include segment metadata.
- Public `NORMAL_PLUS_VISIBLE_QUARANTINE` count now includes segment metadata.
- Aggregate visible IDs are deduplicated when Guarded and Blacklisted overlap.
- `data/historical-identity-baseline.ndjson` is excluded from the final Production ZIP.
- API documentation now includes `reset_request_memo()` and `public_methods()`.

## Tests

- `tests/reporting-consumer-adapter-b1.php`: PASS, 60 passed, 0 failed
- `tests/reporting-foundation-regression.php`: PASS, 104 passed, 0 failed

New segmentation assertions cover:

- public composite IDs expose normal, guarded, blacklisted, and unresolved segments
- public composite count preserves segment metadata
- aggregate visible IDs are deduplicated
- business count remains normal canonical count

## Package Hygiene

- Historical NDJSON runtime dependency: not required for normal B.1 runtime
- Historical NDJSON in final ZIP: NO
- Package hygiene report: `PACKAGE-HYGIENE-v0.8.56.1.md`

## Mutation / Safety

- Consumers migrated: 0
- Schema mutation: NO
- Domain mutation: NO
- Guard mutation: NO
- Blacklist mutation: NO
- Customer mutation: NO
- CustomerID mutation: NO
- Invoice mutation: NO
- Phone mutation: NO
- Assignment mutation: NO
- Case mutation: NO
- Interaction/activity/audit mutation: NO
- Run #7 continuation: NO
- Run #8 implemented: NO
- Run #8 executable: NO
- Frozen Plan live dependency: NO

## Upgrade

- Direct `0.8.55.2 -> 0.8.56.1`: code-only metadata path
- Direct `0.8.56.0 -> 0.8.56.1`: code-only metadata path

## Notes

PHP CLI emits local startup warnings for missing XML extensions in this environment. These are not lint or test failures.
