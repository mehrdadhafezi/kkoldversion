# QA Report v0.8.59.4

## Release

Deficiency Portal Workflow Fixes

## Scope

- Delete portal users safely.
- Prevent `/biawin/?bcrm_view=deficiency&tab=assigned&s=...` from becoming a WordPress search/404 path.
- Return deficiency assignments by selected agent.
- Attach multiple receipts when sending a deficiency case to finance.
- Make deficiency/finance KPI cards clearer and self-explaining.

## Changes Verified

- `admin_post_bcrm_delete_portal_user` deletes only non-admin portal users and blocks self-delete.
- Portal request normalization moves `s` to `bcrm_s` when `bcrm_view` is present.
- Deficiency search forms emit `bcrm_s`.
- Assignment return requires `return_agent_id`.
- Assignment return only accepts selected cases currently assigned to that agent in the assigned status.
- Send-to-finance loops over `receipt_amounts[]` and `case_documents[]`.
- Each uploaded receipt is saved as a separate attachment.
- KPI cards use department/status-specific SQL and include `?` help tooltips.

## Safety

- No customer data migration.
- No deficiency case data migration.
- No schema mutation added for v0.8.59.4.
- No Guard change.
- No Run #7 continuation.
- No Run #8 implementation.

## Tests

- `php tests/deficiency-portal-workflow-fixes-v08594.php`
- `php tests/deficiency-supervisor-assignment-ui-v08593.php`
- `php tests/deficiency-role-capability-sync-v08592.php`
- All PHP tests under `tests/`
- All PHP source/test lint checks

## Environment Note

The local PHP CLI emits startup warnings for unavailable `xml`, `xmlreader`, and `xmlwriter` extensions. The tests and lint checks complete successfully despite those environment warnings.

## Verdict

PASS — READY FOR INDEPENDENT RELEASE AUDIT.
