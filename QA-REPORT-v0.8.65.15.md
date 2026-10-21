# QA Report — 0.8.65.15

Scope: backward-compatible finance-return follow-up access repair.

- Shared capability marker bumped to 7 because marker 6 was already occupied on some prior installs.
- Added feature-scoped one-time compatibility repair option `biawin_crm_finance_return_followup_cap_v1`.
- Existing deficiency-agent/supervisor roles receive `process_finance_return_followup` additively when absent.
- Deleted roles are not recreated; explicit false capability is not overwritten.
- UI and backend accept the stable existing `process_deficiency_case` permission for the currently assigned deficiency agent, so prior installations do not depend solely on the new capability.
- Runtime acceptance on staging remains required.
