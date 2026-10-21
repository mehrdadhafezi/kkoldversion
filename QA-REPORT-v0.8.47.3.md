# QA Report — v0.8.47.3 Transaction Lock/Revalidation Hotfix

## Scope
This hotfix closes the race window between read-only Pilot Preflight and the first mutating write. Pilot scope remains exactly 10 profiles and 20 known legacy records.

## Safety changes
- Re-runs full preflight inside the transaction.
- Locks source and target customer rows.
- Locks and revalidates target phone/invoice identity evidence.
- Locks all source/target assignment rows and blocks any active-assignment conflict under lock.
- Locks each planned legacy row and recomputes the projected Golden hash under lock.
- Any mismatch aborts and rolls back the full transaction.
- Existing journal, post-commit verification, manual confirmation phrase, and rollback hash guard remain enabled.

## Mutation boundary
- No invoice ownership changes.
- No phone ownership changes.
- No CustomerID changes.
- No merge changes.
- Only the same planned Activity/Assignment records may move if every under-lock guard passes.

## QA
- PHP lint: PASS on all PHP files.
- JavaScript syntax check: PASS.
- Package root: `biawin-crm-integration/`.
