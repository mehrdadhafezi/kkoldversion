# QA Report v0.8.39

Focus: repair-only ownership runs and stale phone ownership after invoice moves.

## Regression fixed
- A preview containing only `already_correct` invoices must still expose the execute/repair action.
- Execution must call identity repair for those target profiles even when no invoice ownership move is required.
- Non-manual phone rows not represented by the profile's owned invoice phones are detached from the old profile.
- Manual and manual_invoice_bundle phone rows are not detached by this repair.

## Known sample expected after execution
- `09106864006` resolves to `C060368` after its invoices `1414930` and `1416859` are processed/repaired.
- `C001650` is rebuilt from invoice `1135777` and should use `09175003250` from its invoice phone data.

## Static checks
- PHP lint all plugin PHP files.
- JavaScript syntax check for admin.js.
