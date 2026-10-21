# QA Report — v0.8.65.7

## Scope
- Deficiency action `complete_corrected` is now an explicit **declared amount correction** action.
- UI contains only `مبلغ اعلامی اصلاح‌شده`; the former `مبلغ تایید شده نهایی` field is not shown for this action.
- The common description field remains available.
- The correction is persisted to the latest interaction (canonical declared amount source), recalculated into the customer profile, and synchronized into the active deficiency case snapshot.
- Exact equality rule: corrected declared amount == live invoice total (to cent precision) completes the customer profile and closes the deficiency case.
- If values differ, profile remains incomplete and the deficiency case remains in its current deficiency status, ready for later receipt upload / finance submission.
- Existing receipts no longer block correcting the declared amount.

## Validation
- PHP lint passed for all plugin PHP files.
- Version bumped to 0.8.65.7.
