# QA Report — v0.8.47.1 Controlled Forward Reconstruction / Pilot Commit

## Scope
This is the first mutating reconstruction build. It intentionally does **not** expose a bulk commit.

Pilot scope is frozen to 10 low-complexity deterministic profiles selected from the v0.8.46 `PRESERVE_AND_COMMIT` set because the v0.8.46 `AUTO_COMMIT` set has zero historical operational records in the bundled healthy baseline. A meaningful reconstruction pilot therefore uses exact historical record IDs from the healthy backup and preserves them by re-parenting only those exact rows.

## Golden snapshot
- `bcrm-backup-20260828-162905-manual.ndjson`
- created at `2026-08-28 16:29:05`
- plugin version at backup: `0.8.46`

The large backup is **not bundled**. The plugin contains only hashes, target identity evidence, invoice/phone expectations and exact legacy record IDs required for the 10-profile pilot.

## Pilot selection
Exactly 10 profiles:
- C000082 -> C059061
- C000092 -> C059066
- C000097 -> C059069
- C000101 -> C059072
- C000110 -> C059091
- C000171 -> C059135
- C000283 -> C059203
- C000475 -> C059336
- C000477 -> C059338
- C000478 -> C059339

Each has exactly two legacy rows in the healthy backup: one `activities` row and one `assignments` row. No pilot profile is involved in a recorded manual merge in the Golden snapshot; all targets existed, target name/phone/invoice evidence matched, and targets had no active assignment in the Golden snapshot.

## Domain writes allowed by Pilot
- `activities.customer_id`: old internal ID -> target internal ID
- `activities.object_id`: same move only when `object_type=customer` and it points to the source customer
- `assignments.customer_id`: old internal ID -> target internal ID
- `customers.status` only to maintain active-assignment consistency:
  - source `assigned` -> `new` only when no active assignment remains
  - target -> `assigned` only when the moved assignment is active

## Explicitly NOT changed
- CustomerID / `source_customer_id`
- display name
- phones
- invoices / invoice ownership
- payments
- declared_paid / total_paid / financial aggregates
- manual merge records
- post-Golden expert records
- any profile outside the frozen 10

## Hard preconditions
Commit is all-or-nothing and is refused unless all 10 PASS:
- live v0.8.46 Final Safe Set still resolves the profile as `PRESERVE_AND_COMMIT` with `EXPERT_ACTIVITY`
- no manual merge on source or target
- no source/target human activity after v0.8.45 snapshot
- source and target customer rows exactly match Golden hashes
- no relevant customer-linked row changed after Golden snapshot
- target CustomerID/name match frozen plan
- target phone overlap matches expected identity
- every expected invoice exists and belongs to target
- every legacy row is still at source and matches Golden hash
- no active-assignment conflict on source or target

## Commit safety
- user must type `COMMIT-PILOT-10`
- server reruns preflight immediately before transaction
- customer Golden hashes are rechecked under row locks
- every write is journaled with full before/after JSON and SHA-256 hashes
- post-commit verification must pass or transaction rolls back
- rollback is available only when every current row still matches its recorded after-hash

## Journal tables
Created only when Commit is attempted:
- `wp_bcrm_reconstruction_runs`
- `wp_bcrm_reconstruction_journal`

## QA executed
- PHP lint: PASS for all PHP files
- `node --check assets/admin.js`: PASS
- `node --check assets/frontend.js`: PASS
- frozen pilot JSON: 10 profiles
- offline Golden core checks: PASS for all 10
- pilot targets: current identity/phone/invoice evidence validated against Golden snapshot

## Operational instruction
Production operator should run **Pilot Preflight only first** and send the result for review. Do not press Commit until the preflight output has been reviewed.
