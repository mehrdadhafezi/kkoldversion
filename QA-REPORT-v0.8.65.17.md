# QA Report — v0.8.65.17

## Scope
Restore the legacy contact outcomes that were unintentionally hidden in v0.8.65.16 when `ثبت پیگیری` became available across active deficiency tabs.

## Expected behavior
- `مرحله اقدام اولیه → ثبت تماس` always includes `عدم پاسخ` (`no_answer`).
- `مرحله اقدام اولیه → ثبت تماس` always includes `در انتظار ارسال فیش` (`awaiting_receipt`).
- `ثبت پیگیری` remains available according to the v0.8.65.16 permission/scope rules and still contains its five approved follow-up statuses.
- No workflow state, finance review logic, assignment rule, or receipt upload rule is changed by this patch.

## Static checks
- The contact form calls `render_contact_outcome_options()` without excluding `no_answer` or `awaiting_receipt`.
- Both keys remain present in the fallback contact outcome map and in Workflow Service contact outcomes.
