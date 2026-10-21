# QA REPORT — v0.8.65.13

## Scope
Returned-from-finance allocation and returned-finance initial-action follow-up UX.

## Static acceptance
- `RETURNED_TO_DEFICIENCY` is assignable/reassignable by the deficiency supervisor and assignment preserves the current workflow state.
- The `returned` tab exposes the same selected-row allocation control as other active deficiency tabs, with returned-finance-specific copy.
- Returned-finance cases show `ثبت پیگیری` in `مرحله اقدام اولیه`.
- `ثبت پیگیری` contains exactly the five requested outcomes: approved amount agreed, extra payment claim, no answer, awaiting receipt resend, send to finance with more receipts.
- Duplicate returned-finance meanings are not shown in the generic contact/result controls for a returned case.
- A returned case cannot use the generic send-to-finance path; it must use the explicit follow-up outcome, and the existing new-receipt requirement remains enforced.

Runtime browser acceptance remains required after staging install.
