# QA REPORT — v0.8.65.12

## Scope
Deficiency per-tab assignment, finance-return follow-up outcomes, deficiency-supervisor receipt deletion, profile finance receipt totals.

## Static acceptance
- `RETURNED_TO_DEFICIENCY` is excluded from automatic/manual deficiency assignment guards.
- Reassignment of active deficiency cases preserves current workflow state.
- Returned-from-finance cases expose five follow-up choices.
- `send_more_receipts` requires at least one new receipt before returning to finance.
- Receipt deletion requires `manage_deficiency_cases` or `bcrm_manage_cases`, is restricted to active deficiency department cases, deletes linked review and writes timeline/audit action.
- Profile approved/rejected receipt totals are derived read-only from existing attachments/reviews.

Runtime browser acceptance remains required after staging install.
