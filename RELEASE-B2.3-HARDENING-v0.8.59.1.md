# Biawin CRM v0.8.59.1

Release: B.2.3 Workload/Kartable Production Hardening
Base: v0.8.58.0
Failed Candidate Replaced: v0.8.59.0

## Scope

This release fixes only independent audit blockers from v0.8.59.0. It does not expand scope to B.2.4 and does not touch Production, schema, data repair, Guard, Run #7, or Run #8.

## Hardening Changes

- Filter-current Kartable workload counts now use `management_assignment_filter_summary()`.
- Filter-current candidate rows now use `management_assignment_candidate_rows()`.
- Manager active assignment lists expose `total_count` and `returned_count`.
- Guarded active assignment lists expose `total_count` and `returned_count`.
- Agent assignment rows expose `total_count` and `returned_count`.
- Management workload aggregates require proven scope metadata.
- Tampered department and organization scope requests fail closed.
- Trusted per-agent Guarded workload is rendered as a trusted number.

## Upgrade Safety

- `0.8.58.0 -> 0.8.59.1`: code-only.
- `0.8.59.0 -> 0.8.59.1`: code-only.

No `activate()`, `dbDelta()`, schema migration, data migration, assignment repair, or Guard sync is executed for those exact paths.

## Mutation Safety

- Schema Mutation: NO
- Domain Mutation: NO
- Guard Mutation: NO
- Assignment Mutation: NO
- Run #7 Continued: NO
- Run #8 Implemented: NO
- Run #8 Executable: NO
- Frozen Plan Live Dependency: NO
