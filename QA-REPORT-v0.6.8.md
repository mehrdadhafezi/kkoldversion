# QA v0.6.8

Checks performed:
- PHP syntax for all plugin PHP files.
- JavaScript syntax for frontend/admin assets.
- Verified import mapping includes seller action date.
- Verified cohort rule is conservative: all invoices must have year 1404; mixed 1404/1405 or missing year => normal.
- Verified assignment queries enforce active cohort tab.
- Verified 27 default roster entries: 1 supervisor + 26 agents.
