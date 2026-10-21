# QA Report — v0.8.47.2 Generated Column Hash Guard Hotfix

## Root cause
All 10 pilot rows were falsely blocked because `assignments.active_customer_guard` is a generated database column added after the frozen Golden manifest. It was absent from the manifest row but present in live `SELECT *`, so full-row hashing reported a change even when all persisted operator-controlled fields matched.

## Fix
- Legacy preflight hash is now projected onto the exact fields frozen in `expected_row`.
- Diagnostic diff only compares manifest fields, so extra schema/generated columns cannot masquerade as human edits.
- Rollback now skips generated columns when constructing UPDATE data; generated values are allowed to recompute from their source fields.
- Full journal hashes remain intact for post-commit tamper detection.
- No safety precondition was removed for customer identity, target evidence, merges, post-Golden activity, or active assignment conflicts.

## Expected next preflight
If there are no real changes, the 10 prior assignment hash failures should disappear. Any mismatch in a manifest field still blocks the entire pilot.
