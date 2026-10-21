# QA Report — v0.8.48 Controlled Batch 50

## Scope

- 50 deterministic low-complexity profiles.
- 100 exact legacy rows: 50 activities + 50 assignments.
- All 50 are PRESERVE_AND_COMMIT / EXPERT_ACTIVITY / USE_EXISTING_VALID_TARGET.
- No manual merge membership in the frozen post-pilot snapshot.
- No invoice, phone, CustomerID, merge, payment, declared-paid, interaction, complaint, case, tag, or attachment mutation is implemented by this batch.

## Frozen snapshots

- Healthy historical baseline: bcrm-backup-20260818-142835-manual.ndjson
- Healthy baseline SHA256: 103d40ee75f2074478545ce8646e630645a87b785f2599b53593fe43bf73703f
- New Golden Snapshot after validated pilot: bcrm-backup-20260829-072950-manual.ndjson
- Golden created_at: 2026-08-29 07:29:50
- Golden SHA256: 7bda70995da9ac240dd41f3e8e324b1b034f097e10efac1d18bbb9571c326923
- Frozen batch manifest SHA256: d35106b0de8c6a7a3bbe7622968d2f5bd094d6b15e06266e5d900c20e91e0319

## Static plan audit

PASS:

- 50 unique source internal IDs.
- 50 unique target internal IDs.
- No source/target overlap in batch.
- 100 unique exact record references.
- Every profile has exactly one historical activity and one historical assignment and no other historical domain record in the healthy baseline selection class.
- Every exact record is still located at the frozen source in the post-pilot Golden Snapshot.
- Every projected exact-row hash matches the historical baseline.
- Every source and target full-row hash matches the post-pilot Golden Snapshot.
- Every target has frozen phone evidence and invoice evidence.
- Every target has zero active assignment in the Golden Snapshot.
- Every planned assignment is active (assigned) and each source has exactly one active assignment in the Golden Snapshot.
- No selected source or target belongs to a recorded manual merge component in the Golden Snapshot.

## Runtime safety

- Read-only Preflight must return exactly 50 PASS and 0 BLOCKED.
- Current Final Safe Set must still classify every source as PRESERVE_AND_COMMIT + EXPERT_ACTIVITY with no merge conflict.
- Any source/target domain activity newer than the Golden Snapshot blocks the entire batch.
- Full source and target customer hashes must match the frozen Golden Snapshot.
- Exact legacy rows use projected hashes so generated DB columns cannot cause false failures.
- Target phone evidence now requires all frozen expected phone values to remain present.
- Every frozen target invoice must still be owned by the target.
- Commit re-runs Preflight inside the transaction.
- Customer identity rows are locked and hashes revalidated.
- Target phone/invoice evidence is locked and revalidated.
- Manual merge and post-Golden activity guards are rechecked after customer locks.
- Source/target assignment rows are locked; target must have no active assignment and source may have no other active assignment.
- Every exact legacy row is locked and hash-revalidated immediately before mutation.
- Batch is all-or-nothing: any failure rolls the transaction back.
- Every applied row and customer-status change is journaled with before/after JSON and SHA256 hashes.
- Post-commit verification requires exactly one active assignment at each target, none at source, exact identity/evidence, and customer status consistency.
- Rollback is hash-guarded and scoped only to mode=controlled_batch_50; it cannot accidentally select the validated v0.8.47.4 pilot run.

## Syntax QA

- PHP lint: PASS for main plugin file.
- PHP lint: PASS for controlled reconstruction class.
- node --check assets/admin.js: PASS.
