# QA Report — v0.8.50.2 Run State Recovery Hotfix

## Purpose
Recover Run #5 bookkeeping only after strict proof that Chunk 1 was already committed, and add journal/live idempotency recovery for future stale-progress cases.

## Production evidence used
- Run UUID: `2041c84f-ea80-4688-b7a5-221f95ce962f`
- Run #5 journal supplied by operator: 150 rows = 100 activities + 50 assignments, all applied.
- Journal record refs exactly match frozen plan Chunk 1 (50 profiles / 150 records).
- Post-event backup supplied by operator was independently audited before this build: all 150 rows are at expected targets.

## Recovery contract
`Recover Run #5 State` is read-only against domain tables. It writes only `wp_bcrm_reconstruction_runs` and only when:
- exact Run UUID + mode + manifest hash match;
- current state is exactly 0 profiles / 0 chunks / 0 records;
- run journal has exactly the 150 expected Chunk-1 refs and no extras;
- every journal row is `applied`;
- journal before/after owners match plan source/target;
- every live exact row exists at target;
- Activity `object_id` is at target when object_type=customer;
- every live row full hash equals Journal `after_hash`.

On proof success only bookkeeping becomes 50/500, 1/10, 150 records, 150 verified, status `committing`, next_index=50.

## Runner hardening
- Version-scoped transient keys moved to v08502.
- Before a new chunk, the runner checks whether the next chunk is already fully journaled and live-at-target. If so, it advances bookkeeping only and returns without committing another chunk in the same request.
- Run progress UPDATE must affect exactly one row.
- Progress is read back and verified before COMMIT.
- Existing exact-record locks, hash guards, journal, verification and safety block behavior are retained.

## Explicitly unchanged scope
Recovery never writes Activity, Assignment, Invoice, Phone, CustomerID, Merge, Payment, declared_paid or customer identity data.
