# QA REPORT — v0.8.65.14

## Scope
Returned-finance follow-up access for deficiency agents.

## Static acceptance
- `bcrm_deficiency_agent` receives `process_finance_return_followup`.
- `bcrm_deficiency_supervisor` keeps access and also receives the explicit capability.
- capability marker bumped to v6 so existing installations can provision the new capability through the additive role/cap sync.
- Backend `record_finance_return_followup()` accepts the new capability in addition to legacy operational/manager caps.
- The `ثبت پیگیری` option and returned-finance follow-up section are visible to authorized deficiency agents as well as supervisors.
- Existing five follow-up outcomes and the new-receipt requirement for send-more-receipts remain unchanged.

Runtime acceptance remains required after staging install with an actual deficiency-agent account assigned to a `RETURNED_TO_DEFICIENCY` case.
