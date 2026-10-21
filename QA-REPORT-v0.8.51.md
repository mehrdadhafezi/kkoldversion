# QA v0.8.51 — Fresh Production Safe Set + Controlled Job 1000 Planner

- Source: v0.8.50.4 production audit build.
- New planner is read-only for CRM domain tables; no commit/rollback handler exists in the new class.
- Candidate source: live v0.8.46 Final Safe Set stream.
- Hard filters: PRESERVE_AND_COMMIT / EXPERT_ACTIVITY / USE_EXISTING_VALID_TARGET, no manual merge/conflict, no human activity after v0.8.45, exact 2 Activities + 1 returned Assignment at source, no other operational-domain source records, no active assignment on source/target, live target identity + invoice/phone evidence.
- Deterministic order: old_internal_id ascending.
- Maximum selection: 1000; safety constraints are never weakened to fill the target.
- Output: transient report + downloadable frozen candidate JSON/CSV. No CRM domain mutation.
- PHP lint: PASS for new class, admin registration, audit page, plugin bootstrap.
