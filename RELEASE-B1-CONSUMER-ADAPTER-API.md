# Biawin CRM v0.8.56.0
# Release B.1 Consumer Adapter API

This document describes the shared reporting consumer adapter introduced in Release B.1 and hardened in v0.8.56.1. The adapter is read-only infrastructure for B.2-B.7. It does not migrate legacy dashboards, exports, Kartable/workload UI, expert performance UI, financial reports, case reports, or REST consumers.

## Result Contract

Every public method returns a structured result with:

`status`, `trusted`, `population`, `freshness`, `source`, `generated_at`, `data`, `value`, `error`, and `dependency_status`.

Allowed public statuses are `AVAILABLE`, `DEGRADED`, `BLOCKED`, `UNAVAILABLE`, `SEMANTICALLY_UNRESOLVED`, and `NOT_APPLICABLE`.

Unknown, unavailable, or semantically unresolved metrics return `trusted=false` and `value=null`. Unknown is never represented as trusted zero.

## Public Methods

| Method | Purpose | Input | Scope Requirements | Population Semantics | Source SOT | Freshness | Trust / Failure Behavior | Batch Behavior | Security Behavior | Future Surfaces |
|---|---|---|---|---|---|---|---|---|---|---|
| `reset_request_memo()` | Clear adapter and population request-scope memoization | none | internal/test/admin diagnostic only | not population-specific | adapter + Population SOT memo stores | request-time | cached failures are cleared only for the current request/test lifecycle | not applicable | does not grant data access | test/diagnostic support |
| `public_methods()` | Return the adapter public method inventory | none | documentation/test support | not population-specific | adapter class | static | no business truth returned | not applicable | no data access | QA/API traceability |
| `population_summary($scope)` | Shared population status and segment metadata | scope context | admin, manager, auditor | `NORMAL_CANONICAL` plus visible Guard/quarantine segmentation | `Biawin_CRM_Reporting_Population_Service` | live read | Guard/blacklist dependency failure blocks trusted canonical | memoized per request | agent cannot call broad population | RB-S01, RB-S02, RB-S24 |
| `customer_population_ids($population,$scope,$limit,$offset)` | Return customer IDs for an explicit population | population enum, scope, paging | admin, manager, auditor | supports `ALL_LIVE`, `NORMAL_CANONICAL`, `GUARDED`, `BLACKLISTED_LIVE`, `UNRESOLVED`, `NORMAL_PLUS_VISIBLE_QUARANTINE`; composite output includes `segments.normal_canonical`, `segments.guarded`, `segments.blacklisted_live`, and `segments.unresolved` plus deduplicated aggregate visible IDs | Population SOT | live read | unsupported/unavailable population is untrusted; composite Guard/blacklist failures fail closed | bounded by caller limit/offset when supported | broad request denied for ordinary agent | RB-S01, RB-S02, RB-S24, RB-S34 |
| `customer_population_count($population,$scope)` | Structured population count | population enum, scope | admin, manager, auditor | same as IDs; composite count preserves segment metadata and labels aggregate visible count separately from normal business count | Population SOT | live read | failed dependency returns null, not zero | reuses ID source | broad request denied for ordinary agent | RB-K001-RB-K008 |
| `customer_population_membership($customer_id,$scope)` | Classify one customer into visible segments | customer id, scope | customer-specific allowed scope | segmented, not valid/invalid only | Population SOT | live read | dependency failure blocks classification | request memoized sources | agent must be scoped to customer | RB-S26, RB-S31, RB-S34 |
| `customer_financial_snapshot($customer_id,$scope)` | One customer authoritative financial snapshot | customer id, scope | finance/admin/manager or scoped customer access | customer-specific | Financial SOT expressions plus live tables | live read | failed query returns blocked/null | delegates batch method | scope checked before read | RB-S03, RB-S26, RB-S29 |
| `financial_snapshot_for_customers($customer_ids,$scope)` | Batch actual/declared/discrepancy snapshots | customer ids, scope | finance/admin/manager | explicit customer set | `Biawin_CRM_Reporting_Financial_Service::actual_paid_expression()` plus interactions latest declared semantics | live read | partial or failed chunk is untrusted | chunks by 500; no per-customer N+1 | unauthorized scope blocked | RB-S03, RB-S22, RB-S26, RB-S29, RB-S30 |
| `financial_total_for_population($population,$filters,$scope)` | Population actual paid total foundation | explicit population and filters | finance/admin/manager | target population must be trusted | Population SOT + Financial SOT | live read | ambiguous date filter is semantically unresolved | uses batch snapshots | scope checked and filters validated | RB-S03, RB-S22 |
| `approved_paid_total($filters,$scope)` | Compatibility placeholder for unresolved approved paid | filters, scope | finance/admin/manager | undefined | business contract | not applicable | always `SEMANTICALLY_UNRESOLVED`, `trusted=false`, `value=null`, `APPROVED_PAID_SOURCE_UNDEFINED` | not applicable | does not invent permissioned truth | RB-K financial approved-paid rows |
| `assignment_workload_summary($scope)` | Shared assignment workload summary | scope | admin, manager, auditor | newest active assignment per customer | Assignment SOT | live read | blocked if workload dependencies fail | service summary | no event actor attribution | RB-S02, RB-S04 |
| `newest_active_assignments_for_customers($customer_ids,$scope)` | Batch owner/workload lookup | customer ids, scope | admin, manager, auditor | newest active assignment per customer | Assignment SOT condition | live read | failed chunk blocks result | chunks by 500 | owner is not event actor | RB-S02, RB-S04, RB-S29 |
| `workable_active_workload($scope)` | Normal canonical active workload | scope | admin, manager, auditor | excludes Guarded | Assignment SOT + Population SOT | live read | blocked if canonical/Guard unavailable | summary reuse | ordinary expert workload excludes Guarded | RB-S04 |
| `guarded_active_workload($scope)` | Guarded active workload segment | scope | admin, manager, auditor | Guarded separate | Assignment SOT + Guard source | live read | blocked if Guard unavailable | summary reuse | manager/data-quality visibility only | RB-S04 |
| `unassigned_normal_canonical($scope)` | Unassigned canonical customer count | scope | admin, manager, auditor | `NORMAL_CANONICAL` only | Assignment SOT + Population SOT | live read | blocked if canonical unavailable | service helper | broad agent access denied | RB-S02 |
| `agent_workload($agent_user_id,$scope)` | Owner workload for one agent | agent id, scope | admin, manager, auditor or scoped agent | ownership only | Assignment SOT | live read | empty valid result can be trusted zero | service grouped result | explicitly warns owner != actor | RB-S04, RB-S23 |
| `event_report($filters,$scope)` | Event/audit reporting foundation | explicit filters | admin, manager, auditor | events, not population truth | interactions, activities, audit_log | live read | missing tables/query failures block | aggregate read; future detail pagination expected | actor comes from event source, not current owner | RB-S23, RB-S25, RB-S33 |
| `case_population_summary($case_type,$scope)` | Case count with orphan context | case type, scope | case-domain role or admin/manager/auditor | canonical case metrics exclude orphans | live case tables + customers | live read | orphan dependency failure degrades/blocks | aggregate read | domain-specific scope only | RB-S11-RB-S21, RB-S31 |
| `case_customer_validity($case_type,$customer_id,$scope)` | Classify customer reference | case type, customer id, scope | case-domain role or scoped access | `NORMAL_CANONICAL_CUSTOMER`, `GUARDED_CUSTOMER`, `BLACKLISTED_LIVE_CUSTOMER`, `ORPHAN_CUSTOMER_REFERENCE`, `UNKNOWN_DEPENDENCY_STATE` | Population SOT | live read | dependency failure returns unknown dependency state | customer-specific | no repair/reassignment | RB-S31 |
| `case_rows($case_type,$filters,$scope,$limit,$offset)` | Read-only case rows foundation | type, filters, scope, page | case-domain role or admin/manager/auditor | row context only | live case table | live read | query failure blocks | bounded page limit max 500 | no mutation/action buttons | RB-S11-RB-S21 |
| `case_orphan_summary($case_type,$scope)` | Live orphan reference count | case type, scope | case-domain role or admin/manager/auditor | orphans separate from canonical case metrics | case table + customers | live read | dependency failure blocked/null | aggregate read | no repair | RB-S31 |
| `case_guard_summary($case_type,$scope)` | Guarded case reference count | case type, scope | case-domain role or admin/manager/auditor | Guarded separate | Guard source + case table | live read | Guard failure blocked/null | chunks by 500 | data-quality visibility only | RB-S31 |
| `normalize_export_row($row)` | Future export row sanitizer foundation | array row | serialization-time only | not population-specific | export contract | serialization | no storage mutation | row-level | formula injection neutralization only at output | RB-S26, RB-S27, RB-S28, RB-S29, RB-S30 |
| `sanitize_export_cell($value)` | Neutralize dangerous spreadsheet prefixes | scalar value | serialization-time only | not population-specific | export contract | serialization | safe cells unchanged | cell-level | prefixes `=`, `+`, `-`, `@` prefixed with apostrophe | RB-K export rows |
| `normalize_filters($filters,$contract)` | Explicit filter contract validator | allowed filter object | caller-specific | timestamp semantics required when date filters are used | filter contract | request-time | unknown filters blocked, ambiguous date unresolved | not applicable | never accepts SQL fragments | all future migrated consumers |
| `assert_scope($scope,$operation,$object)` | Scope guard for adapter calls | role/scope context | existing roles/capabilities | not population-specific | existing capability/scope conventions | request-time | forbidden scope blocked/null | not applicable | no parallel authorization system | all future migrated consumers |

## Approved Paid

There is no global authoritative `APPROVED_PAID` source in v0.8.56.0. The adapter intentionally returns:

`status=SEMANTICALLY_UNRESOLVED`, `trusted=false`, `value=null`, `reason/error=APPROVED_PAID_SOURCE_UNDEFINED`.

Case-local fields such as `final_approved_amount` remain case-local and are not promoted to global approved paid truth.

## Composite Population Semantics

`NORMAL_PLUS_VISIBLE_QUARANTINE` is a display/composite population only. It is not an undifferentiated business population.

Public ID and count results expose:

- `segments.normal_canonical`: business population.
- `segments.guarded`: separate data-quality segment.
- `segments.blacklisted_live`: separate technical/data-quality segment.
- `segments.unresolved`: unresolved segment, currently mapped to Guard source by the Population SOT.
- `ids` / `value`: deduplicated aggregate visible IDs/count for display only.
- `business_count`: normal canonical count, not the aggregate visible count.

If a customer belongs to both Guarded and Blacklisted, segment membership may show both classifications, while the aggregate visible IDs list contains that customer only once.
