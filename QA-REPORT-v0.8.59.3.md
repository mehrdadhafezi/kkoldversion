# QA Report v0.8.59.3

## Release

Deficiency Supervisor Assignment UI Hardening

## Scope

- Allow deficiency supervisors to assign ready deficiency phone/customer cases to deficiency agents.
- Allow deficiency supervisors to return selected assigned deficiency cases from the assignment page.
- Show primary normalized mobile numbers in selectable deficiency case rows.
- Preserve capability checks and assigned-case restrictions for deficiency agents.

## Changes Verified

- Assignment page renders a ready-to-assign queue.
- Assignment page renders an assigned-for-return queue.
- Return assignment button no longer requires selecting an agent.
- Case assignment rows expose a safe primary mobile column.
- `assign_deficiency_cases` remains the required capability for deficiency assignment and return actions.
- Returned deficiency assignments clear `deficiency_agent_id`, clear `assigned_at`, and return the case to `INCOMPLETE_NEW`.
- Assignment still validates target users with `process_deficiency_case`.

## Safety

- No customer data migration.
- No deficiency case data migration.
- No schema mutation added for v0.8.59.3.
- No Guard change.
- No Run #7 continuation.
- No Run #8 implementation.

## Tests

- `php tests/deficiency-supervisor-assignment-ui-v08593.php`:
  - validates supervisor assignment/return UI contract.
- `php tests/deficiency-role-capability-sync-v08592.php`:
  - validates role/cap repair still covers deficiency supervisors and agents.
- All PHP tests under `tests/`:
  - expected to pass before packaging.
- All PHP source and test files:
  - expected to lint clean before packaging.

## Environment Note

The local PHP CLI may emit startup warnings for unavailable `xml`, `xmlreader`, and `xmlwriter` extensions. These warnings are environmental and do not indicate syntax or test failures.

## Verdict

PASS — READY FOR INDEPENDENT RELEASE AUDIT.
