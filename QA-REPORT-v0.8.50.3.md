# QA Report — v0.8.50.3 Live Conflict Quarantine + Resume Hotfix

## Purpose
Resume existing Controlled Job 500 / Run #5 after Chunk 3 was safety-blocked by a live active-assignment conflict. Preserve committed Chunks 1–2 and quarantine only profiles that are no longer live-safe.

## Safety behavior
- Frozen plan remains `data/controlled-job-v0.8.50.json` (500 profiles / 1500 exact records).
- Existing Run #5 remains resumable; no new run is required.
- Remaining-live revalidation is read-only.
- Preflight now detects active assignments even when the planned historical assignment is `returned`.
- A profile blocked by live preflight is quarantined and receives no domain writes.
- A profile that becomes unsafe under row locks is quarantined before any of its exact records are updated.
- Unknown write/journal/verification failures still rollback the entire current physical chunk.
- Safe profiles in a mixed chunk may commit in the same transaction.
- `next_index` advances by processed plan positions; `profiles_committed` counts only committed profiles; `skipped` counts quarantined profiles.
- Quarantine details are persisted in Run `result_json` with source, target, phase, reasons and detected time.
- Final run status becomes `committed_with_quarantine` when at least one profile was quarantined.
- Invoice / Phone / CustomerID / Merge / Payment / declared_paid are not written by this runner.

## Production-specific check
Frozen plan index 104 (Chunk 3) is source internal `2329` (`C002329`) -> target internal `61199` (`C061015`), matching the source customer reported in `ACTIVE_ASSIGNMENT_CONFLICT_UNDER_LOCK_2329_10737`. v0.8.50.3 will quarantine it if the live conflict still exists instead of replaying/overwriting the active assignment.

## Static QA
- PHP lint: main plugin PASS.
- PHP lint: controlled reconstruction PASS.
- PHP lint: admin class PASS.
- JS syntax: `assets/admin.js` PASS.
- ZIP root: `biawin-crm-integration/`.
