# QA Report — v0.8.42 Forward Reconstruction Dry Run Planner

- Read-only planner added; no CRM domain writes.
- Hard Blacklist remains enforced as a planning constraint.
- Plan classes: SAFE_AUTO, NEW_ID_REQUIRED, TARGET_CREATE_REQUIRED, AMBIGUOUS_MANUAL, CONFLICT_CURRENT_ACTIVITY, NO_ACTION.
- Detects post-baseline activity (where created_at/updated_at exists) after 2026-08-18 14:28:35.
- Reports historical Complete recovery candidates and stranded declared-paid candidates.
- Maintenance safety lock remains enabled by default.
