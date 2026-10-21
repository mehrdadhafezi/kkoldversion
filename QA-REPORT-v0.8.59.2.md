# QA Report v0.8.59.2

## Release

Deficiency Role Capability Sync Hardening

## Scope

- Repair role/cap synchronization for specialist portal roles during code-only upgrades.
- Repair role/cap synchronization before creating portal users.
- Keep Deficiency Resolution visibility tied to `view_deficiency_cases`.
- Preserve assigned-case-only processing for `bcrm_deficiency_agent`.

## Root Cause

The seeded default deficiency agent could work while newly created deficiency agents failed to see the رفع ناقصی section when an existing WordPress role definition was missing specialist capabilities. Recent code-only upgrade gates returned before full activation, so legacy/misaligned role capability rows were not always repaired.

## Changes Verified

- Added `Biawin_CRM_Activator::ensure_roles_and_caps()` as a single idempotent source for CRM role capability definitions.
- `maybe_upgrade()` now calls role/cap sync before code-only upgrade returns.
- v0.8.58.0, v0.8.59.0, and v0.8.59.1 can upgrade to v0.8.59.2 through a code-only gate with capability repair.
- `activate()` uses the same role/cap sync method.
- Portal user creation calls role/cap sync before inserting a user and enforces the selected role after insert.

## Safety

- No customer data mutation.
- No deficiency case mutation.
- No assignment mutation.
- No schema mutation added for v0.8.59.2.
- No Guard change.
- No Run #7 continuation.
- No Run #8 implementation.

## Tests

- `php tests/deficiency-role-capability-sync-v08592.php`:
  - TOTAL_PASS=28
  - TOTAL_FAIL=0
- All PHP test files under `tests/`:
  - ALL_TESTS_PASS
- All PHP source and test files:
  - `php -l`
  - No syntax errors detected.

## Environment Note

The local PHP CLI emitted startup warnings for unavailable `xml`, `xmlreader`, and `xmlwriter` extensions. The executed tests and lint checks completed successfully despite those environment warnings.

## Verdict

PASS — READY FOR INDEPENDENT RELEASE AUDIT.
