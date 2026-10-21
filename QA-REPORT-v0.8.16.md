# QA Report - biawin-crm-integration v0.8.16

Date: 2026-08-13
Environment: Local WordPress QA (`bcrm_qa_0812`) via XAMPP + WP-CLI

## Scope

- Workflow deficiency/finance form UI fix.
- Conditional section display for selected workflow step.
- Styled text, money, date, file, select, and textarea fields.
- Asset cache invalidation by plugin version bump.
- Admin and portal asset loading.

## Results

Passed:
- Plugin activates as v0.8.16.
- PHP lint passed for changed PHP files.
- Deficiency case detail renders without inline workflow script.
- Unified workflow form exists in rendered HTML.
- Calendar inputs render as `type="date"`.
- Money fields render as styled `type="text"` with `inputmode="numeric"`.
- Workflow section controller is loaded in both `frontend.js` and `portal-fixes.js`.
- Admin pages enqueue `portal-fixes.js`, so the same workflow UI behavior is available outside the portal shell.

## Notes

- Browser binding did not return readable inspection output in this environment, so final UI verification was done through WordPress rendered HTML and asset checks.
