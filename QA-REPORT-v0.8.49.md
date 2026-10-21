# QA Report — v0.8.49 Controlled Batch 250

## Scope

- 250 deterministic controlled profiles in the next validated complexity tier.
- 750 exact legacy rows: 500 activities + 250 returned assignments.
- Every profile has exactly two historical activities and one historical assignment, with no other historical domain records in the healthy baseline.
- All 250 are PRESERVE_AND_COMMIT / EXPERT_ACTIVITY / USE_EXISTING_VALID_TARGET.
- This tier is intentionally based on returned assignments: no active assignment is expected at source or target.
- No invoice, phone, CustomerID, merge, payment, declared-paid, interaction, complaint, case, tag, or attachment mutation is implemented.

## Frozen snapshots

- Healthy historical baseline: bcrm-backup-20260818-142835-manual.ndjson
- Healthy baseline SHA256: 103d40ee75f2074478545ce8646e630645a87b785f2599b53593fe43bf73703f
- New Golden Snapshot after validated Batch 50: bcrm-backup-20260829-083804-manual.ndjson
- Golden created_at: 2026-08-29 08:38:04
- Golden SHA256: eeb6906bc158874c4529161f6613387a21ea4ee09a53d82dfcf6ba3d6fd9f5cb
- Frozen batch manifest SHA256: ed361eebb58dc6962e6c3f45715ada11e51fe8d9a2b4f0ad45066c140b83e8e1

## Static plan audit

PASS:

- 250 unique source internal IDs.
- 250 unique target internal IDs.
- No source/target overlap.
- 750 unique exact record references.
- Exact table split is 500 activities + 250 assignments.
- Every planned assignment is historically `returned`; source and target have zero active assignments in the Golden Snapshot.
- Every exact baseline record is still at its frozen source in the post-Batch-50 Golden Snapshot.
- Every projected exact-row hash matches the historical baseline.
- Every source and target full-row hash matches the Golden Snapshot.
- Every target has frozen phone and invoice evidence.
- No selected source or target belongs to a recorded manual merge component.

## Runtime safety

- Read-only Preflight must return exactly 250 PASS and 0 BLOCKED.
- Live Final Safe Set must remain PRESERVE_AND_COMMIT + EXPERT_ACTIVITY with no merge conflict.
- Any source/target domain activity newer than 2026-08-29 08:38:04 blocks the entire batch.
- Full source/target customer hashes and all 750 projected exact-record hashes are frozen.
- Target phone/invoice evidence is checked before commit and again under row locks.
- Commit re-runs Preflight inside the transaction.
- Source/target customer rows, assignments, target evidence and each exact legacy row are lock/revalidated before write.
- All-or-nothing transaction: any failure rolls back the whole batch.
- Every applied row is journaled with before/after JSON and hashes.
- Post-commit verification requires all 750 exact records at target, no active assignment at source/target, preserved target identity/evidence, and status consistency.
- Rollback is hash-guarded and scoped only to `mode=controlled_batch_250`; it cannot select Pilot or Batch 50 runs.

## Important progression note

This is not a blind scale-up of the previous 50-row class. Only 23 unused profiles remained in the exact one-activity/one-assignment class. Therefore v0.8.49 deliberately advances one previously pilot-tested behavior: two activities plus one returned assignment per profile. Returned-assignment behavior was already exercised successfully in two profiles of the validated v0.8.47.4 Pilot. The batch still excludes interactions, cases, complaints, merges, rekeys, replacement IDs, and deleted-invoice restoration.
