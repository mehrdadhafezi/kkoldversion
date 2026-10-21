# QA Report — v0.8.45

- Read-only Expert Intent & Manual Merge Reconciliation Audit added.
- Separates HUMAN_CONFIRMED, HUMAN_INFERRED, SYSTEM_MIGRATION, SYSTEM_AUTOMATION, UNKNOWN evidence.
- Manual merge events are first-class identity evidence and are never auto-committed.
- Adds per-identity reconciliation CSV and per-merge reconciliation CSV.
- No CRM-domain INSERT/UPDATE/DELETE added.
- Existing maintenance safety lock remains enabled by default.
