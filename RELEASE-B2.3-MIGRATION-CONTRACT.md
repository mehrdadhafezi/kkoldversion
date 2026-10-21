# Biawin CRM Release B.2.3 Migration Contract

Version: v0.8.59.0

## Assignment Truth

All migrated workload, Kartable, and assignment-dashboard reporting consumers use the canonical newest-active assignment semantic:

`status='assigned'` and the maximum active assignment row id per customer.

This means one customer contributes at most one active workload unit. Duplicate active assignment rows are diagnostic only and must not increase workload counts.

## Population Contract

- Workable workload: `NORMAL_CANONICAL`.
- Guarded workload: separate `GUARDED` segment, visible to permitted management surfaces and excluded from ordinary expert workload.
- Unassigned workload: `NORMAL_CANONICAL` customers with no canonical active assignment.
- Blacklisted and guarded rows are never hidden inside normal workload totals.

## Adapter Contract

| Method | Consumer | Population | Scope | Failure Policy |
|---|---|---|---|---|
| `management_workload_cards()` | Manager KPI cards and portal manager summary | NORMAL_CANONICAL + separate GUARDED diagnostic | Admin/manager/auditor | Fail closed with `trusted=false` |
| `management_agent_workload_rows()` | Per-agent workload table | NORMAL_CANONICAL plus separate guarded aggregate | Admin/manager/auditor | Fail closed on dependency/query error |
| `management_recent_assignment_rows()` | Active assignment list | NORMAL_CANONICAL | Admin/manager/auditor | Fail closed on dependency/query error |
| `agent_dashboard_assignment_summary()` | Agent dashboard assignment cards | NORMAL_CANONICAL | Same agent only unless manager/admin scope | Fail closed on scope/dependency/query error |

## Scope Contract

- Manager-wide methods remain unavailable to ordinary agent scope.
- Agent dashboard reporting requires matching `agent_user_id` for ordinary agent scope.
- Existing WordPress capability gates are preserved.
- No new role, department, or organization broadening was introduced.

## Mutation Contract

B.2.3 is reporting-only for the migrated surfaces:

- Schema Mutation: NO
- Domain Mutation: NO
- Guard Mutation: NO
- Assignment Mutation: NO
- Run #7 Continued: NO
- Run #8 Implemented: NO

## Package Contract

The official build artifact is:

`biawin-crm-integration-v0.8.59.0-workload-kartable-sot-migration.zip`
