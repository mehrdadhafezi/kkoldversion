# QA Report — v0.8.65.9

## Scope
1. Targeted assignment search remains inside assignment workflow.
2. Finance tables expose assigned finance agent per case.
3. Multi-row invoice support for refund → registered.

## Root causes
- `render_assignment_panel()` embedded `render_case_table('new', ...)`; the nested search form hard-coded `tab=new`, so submitting a search navigated away from `tab=assign`.
- Finance list rows selected `finance_agent_id` but never rendered it as a column.
- Refund registered UI/backend stored only one `invoice_number/customer_phone/invoice_amount`, unlike approval-with-invoice which already used an array in `details_json`.

## Compatibility
- No schema migration required.
- Legacy single refund fields are still read as fallback and are mirrored from the first row on save.
- Existing approval-with-invoice behavior is unchanged.
