# Biawin CRM v0.8.56.1
# Release B.1 Contract Traceability

B.1 targets infrastructure only. No B.0 consumer is migrated in this release. v0.8.56.1 hardens the B.1 adapter contract without implementing B.2. Status values below describe adapter readiness or the later sub-release that must migrate each surface.

## Surface Traceability

| Surface | Adapter Coverage | SOT Dependency | Tests | Status |
|---|---|---|---|---|
| RB-S01 Management dashboard | `population_summary`, `customer_population_count` | Population SOT | FX-POP-001, FX-POP-002, FX-SEC-001 | DEFERRED_TO_B2 |
| RB-S02 Customer list / management population | `customer_population_ids`, `customer_population_membership` | Population SOT | FX-POP-001, FX-SEC-001 | DEFERRED_TO_B2 |
| RB-S03 Financial headline metrics | `financial_total_for_population`, `approved_paid_total` | Financial SOT | FX-FIN-001, FX-FIN-002 | DEFERRED_TO_B5 |
| RB-S04 Assignment workload | `assignment_workload_summary`, `newest_active_assignments_for_customers` | Assignment SOT | FX-ASG-001, FX-ASG-002 | DEFERRED_TO_B3 |
| RB-S05-RB-S10 Admin operational drilldowns | `normalize_filters`, population/assignment/financial helpers | Population, Assignment, Financial SOT | FX-SEC-001 | DEFERRED_TO_B2 |
| RB-S11-RB-S21 Case-domain dashboards | `case_population_summary`, `case_rows`, `case_orphan_summary`, `case_guard_summary` | Case reporting context | FX-CASE-001, FX-CASE-002 | DEFERRED_TO_B6 |
| RB-S22 Legacy financial cartable | `financial_snapshot_for_customers`, `approved_paid_total` | Financial SOT | FX-FIN-001, FX-FIN-002 | BLOCKED_BY_BUSINESS_DECISION |
| RB-S23 Expert performance | `event_report`, `agent_workload` | Event contract, Assignment SOT | FX-EVT-001, FX-EVT-002, FX-EVT-003 | DEFERRED_TO_B4 |
| RB-S24 Population validation | `population_summary` | Population SOT | FX-POP-001, FX-POP-002 | ADAPTER_READY |
| RB-S25 Audit evidence | `event_report` | audit_log evidence | FX-EVT-003 | DEFERRED_TO_B4 |
| RB-S26 Customer CSV/Excel export | `normalize_export_row`, `sanitize_export_cell`, financial/population helpers | Export contract, Population SOT, Financial SOT | FX-EXP-002 | DEFERRED_TO_B7 |
| RB-S27 Data Audit JSON | structured result contract | Validation SOT | reporting foundation regression | DEFERRED_TO_B7 |
| RB-S28 Deficiency CSV | export sanitizer + case context | Case reporting context | FX-EXP-002, FX-CASE-001 | DEFERRED_TO_B7 |
| RB-S29 REST workflow list/export | filter contract, population/financial/case/event helpers | Multiple SOTs | FX-SEC-001 | DEFERRED_TO_B7 |
| RB-S30 REST dashboard | structured adapter contract | Multiple SOTs | FX-SEC-001 | DEFERRED_TO_B7 |
| RB-S31 Case/customer validity | `case_customer_validity` | Population SOT + case tables | FX-CASE-001, FX-CASE-002 | ADAPTER_READY |
| RB-S32 Reporting SOT page | no semantic change | Validation SOT | reporting foundation regression | NO_ADAPTER_CHANGE_REQUIRED |
| RB-S33 Activity/audit double-count diagnostics | `event_report` | interactions, activities, audit_log | FX-EVT-001, FX-EVT-002, FX-EVT-003 | DEFERRED_TO_B4 |
| RB-S34 Future manager list default | population and membership helpers | Population SOT | FX-POP-001, FX-SEC-001 | DEFERRED_TO_B2 |
| RB-S35-RB-S39 Legacy specialized exports/tools | export/filter/scope foundations only | Existing legacy services | static no-migration audit | DEFERRED_TO_B7 |

## KPI Traceability

| KPI IDs | Adapter Method | SOT Dependency | Test ID | Status |
|---|---|---|---|---|
| RB-K001-RB-K008 | population methods | Population SOT | FX-POP-001, FX-POP-002 | ADAPTER_READY |
| RB-K009-RB-K018 | assignment workload methods | Assignment SOT + Population SOT | FX-ASG-001, FX-ASG-002 | ADAPTER_READY |
| RB-K019-RB-K031 | financial methods | Financial SOT + Population SOT | FX-FIN-001, FX-FIN-002 | ADAPTER_READY / BLOCKED_BY_BUSINESS_DECISION for approved paid |
| RB-K032-RB-K045 | case reporting methods | Case reporting context + Population SOT | FX-CASE-001, FX-CASE-002 | ADAPTER_READY |
| RB-K046-RB-K056 | event/audit methods | interactions, activities, audit_log | FX-EVT-001, FX-EVT-002, FX-EVT-003 | ADAPTER_READY |
| RB-K057-RB-K068 | export/filter methods | Export and filter contract | FX-EXP-002 | ADAPTER_READY |
| RB-K069-RB-K078 | security, cache, diagnostics, no-migration gates | Scope/memoization/static audit | FX-CACHE-001, FX-SEC-001 | ADAPTER_READY |

## No Consumer Migration

`CONSUMERS_MIGRATED = 0`

Admin dashboard, customer lists, Kartable/workload UI, expert performance UI, financial reports, case reports, CSV/Excel exports, and REST reporting consumers are not switched to `Biawin_CRM_Reporting_Consumer_Adapter` in B.1.

## v0.8.56.1 Hardening Trace

| Audit Finding | Fix | Test | Status |
|---|---|---|---|
| `NORMAL_PLUS_VISIBLE_QUARANTINE` public IDs dropped segmentation | `customer_population_ids()` now returns `segments`, `business_count`, and deduplicated aggregate IDs for composite population | `segmentation public IDs expose normal/guarded/blacklisted/unresolved segment` | ADAPTER_READY |
| composite public count hid segmentation | `customer_population_count()` now includes composite segment metadata | `segmentation public count preserves segment metadata` | ADAPTER_READY |
| inherited historical NDJSON shipped in ZIP | excluded from v0.8.56.1 production package | package hygiene check | ADAPTER_READY |
| undocumented public methods | documented `reset_request_memo()` and `public_methods()` | API doc audit | ADAPTER_READY |
