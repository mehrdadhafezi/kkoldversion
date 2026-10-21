# QA Report — v0.8.44 Commit Safety Preflight + Record-Level Migration Plan

- Read-only preflight only; no CRM INSERT/UPDATE/DELETE implemented.
- Adds full all-history expert activity ledger CSV.
- Explicitly inventories merge_events performed_by users and marks involved profiles as protected/manual-freeze for future commit.
- Adds record-level migration plan CSV across CRM customer-linked domain tables.
- Post-cutoff or user-attributable records are PRESERVE_EXPERT_AND_MOVE, never overwrite.
- Existing target records are KEEP_TARGET_EXPERT.
- Ambiguous/manual-merge profiles are MANUAL_FREEZE.
- Maintenance safety lock remains enabled by default.
