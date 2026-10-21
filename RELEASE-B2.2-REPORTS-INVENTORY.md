# Biawin CRM v0.8.58.0
# Release B.2.2 — Management Reports SOT Migration Inventory

Base: v0.8.57.1

Scope: Management Reports page summary consumers only.

## Included Consumers

| ID | File/Class | Route/Page | Consumer | Prior Source | B.2.2 Source | Classification |
|---|---|---|---|---|---|---|
| B22-R01 | admin/class-biawin-crm-admin.php / Biawin_CRM_Admin | admin.php?page=biawin-crm-reports | Report KPI cards | direct customers/invoices SQL, customers.total_paid | Reporting Consumer Adapter: management_report_kpi_rows | READY_FOR_MIGRATION |
| B22-R02 | admin/class-biawin-crm-admin.php / Biawin_CRM_Admin | admin.php?page=biawin-crm-reports | Customer preview table | direct customers/phones/invoices/assignments/activities SQL | Reporting Consumer Adapter: management_customer_preview_rows | READY_FOR_MIGRATION |
| B22-R03 | admin/class-biawin-crm-admin.php / Biawin_CRM_Admin | admin.php?page=biawin-crm-reports | Satisfaction summary | direct customers.satisfaction_avg SQL | Reporting Consumer Adapter: management_satisfaction_summary | READY_FOR_MIGRATION |
| B22-R04 | admin/class-biawin-crm-admin.php / Biawin_CRM_Admin | admin.php?page=biawin-crm-reports | Contact status summary | direct interactions SQL | Reporting Consumer Adapter: management_contact_status_rows | READY_FOR_MIGRATION |
| B22-R05 | admin/class-biawin-crm-admin.php / Biawin_CRM_Admin | admin.php?page=biawin-crm-reports | Journey route summary | direct customers/invoices SQL, customers.total_paid | Reporting Consumer Adapter: management_route_summary_rows | READY_FOR_MIGRATION |
| B22-R06 | admin/class-biawin-crm-admin.php / Biawin_CRM_Admin | admin.php?page=biawin-crm-reports | Journey route detail | direct customers/invoices SQL, customers.total_paid | Reporting Consumer Adapter: management_route_detail_rows | READY_FOR_MIGRATION |
| B22-R07 | admin/class-biawin-crm-admin.php / Biawin_CRM_Admin | admin.php?page=biawin-crm-reports | Service case summary | direct service_catalog/service_cases SQL | Reporting Consumer Adapter: management_service_case_summary_rows | READY_FOR_MIGRATION |
| B22-R08 | admin/class-biawin-crm-admin.php / Biawin_CRM_Admin | admin.php?page=biawin-crm-reports | Source service summary | direct services SQL | Reporting Consumer Adapter: management_source_service_summary_rows | READY_FOR_MIGRATION |

## Excluded Consumers

CSV exports, Excel exports, REST reporting, expert performance, workload/Kartable, agent dashboards, detailed case reports, and detailed finance workflow reports are intentionally unchanged in B.2.2.

## Highest Risks

- Customer preview table previously mixed customer, invoice, assignment, and activity reads inside the page. It is now adapter-mediated and fail-closed, but remains a wider read surface than KPI cards.
- Source services remain source-import domain, not revenue truth.
- Service case credit remains case/service domain, not financial revenue.
