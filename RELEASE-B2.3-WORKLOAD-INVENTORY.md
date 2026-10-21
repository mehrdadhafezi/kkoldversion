# Biawin CRM Release B.2.3 Workload Inventory

Version: v0.8.59.0
Release: Workload and Kartable SOT Migration
Base: v0.8.58.0
Mode: Local audit plus implementation

## Consumers Found

| ID | File/Class | Method/Page | Visible KPI/List | Previous Source | Replacement Adapter | SOT | Status | Acceptance Test |
|---|---|---|---|---|---|---|---|---|
| B23-01 | `admin/class-biawin-crm-admin.php` / `Biawin_CRM_Admin` | `assignments_page()` | Assignment/Kartable KPI cards | Direct assignment/customer queries | `management_workload_cards()` plus `customer_population_count()` | Reporting Consumer Adapter + Canonical Assignment Adapter | MIGRATED | KPI source and fail-closed rendering |
| B23-02 | `admin/class-biawin-crm-admin.php` / `Biawin_CRM_Admin` | `assignments_page()` | Per-agent workload table | Direct grouped assignment counts | `management_agent_workload_rows()` | Newest active assignment per customer | MIGRATED | Duplicate active rows do not double count |
| B23-03 | `admin/class-biawin-crm-admin.php` / `Biawin_CRM_Admin` | `assignments_page()` | Recent active assignments list | Direct newest rows in page | `management_recent_assignment_rows()` | Newest active NORMAL_CANONICAL assignments | MIGRATED | Card/list reconciliation and bounded read |
| B23-04 | `includes/class-biawin-crm-frontend.php` / `Biawin_CRM_Frontend` | `render_front_dashboard()` manager branch | Manager unassigned workload card | Dashboard direct assignment SQL/cache | `management_workload_cards()` | NORMAL_CANONICAL unassigned | MIGRATED | No direct assignment SQL in portal dashboard |
| B23-05 | `includes/class-biawin-crm-frontend.php` / `Biawin_CRM_Frontend` | `render_front_dashboard()` agent branch | Agent assignment KPIs | Direct `Biawin_CRM_DB::table('assignments')` count | `agent_dashboard_assignment_summary()` | Agent-scoped newest active NORMAL_CANONICAL | MIGRATED | Agent scope and fail-closed value rendering |
| B23-06 | `admin/class-biawin-crm-admin.php` / `Biawin_CRM_Admin` | B.2.1 management dashboard surface | Existing management workload equivalents | B.2.1 SOT adapter | Preserved shared adapter semantics | Same population/assignment semantic | RECONCILED | Cross-surface consistency |

## Out Of Scope

- Expert performance scoring.
- CSV and Excel exports.
- REST reporting.
- Detailed finance reports.
- Detailed case reports.
- Assignment mutation flows such as save, return, and sales-manager mapping.

## Highest Risks

- Candidate filtering inside the assignment operation page remains a business workflow query and was not migrated because it is tied to assignment execution, not reporting truth.
- Department/team workload summaries continue to depend on the existing WordPress capability and user-scope boundary. No schema or role expansion was introduced.
- Guarded workload visibility depends on the live Readiness Guard table being available. If the dependency is unavailable, reporting fails closed instead of rendering zero.
