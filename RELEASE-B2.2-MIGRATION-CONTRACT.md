# Biawin CRM v0.8.58.0
# Release B.2.2 — Management Reports SOT Migration Contract

## Contract

Management Reports rendering code must not contain new business SQL. It may call Reporting Consumer Adapter methods and render only adapter-returned structured values.

## Adapter Methods

| Consumer | Adapter Method | Population/SOT | Financial Semantic | Failure Policy |
|---|---|---|---|---|
| Report KPI cards | management_report_kpi_rows | NORMAL_CANONICAL | ACTUAL_PAID_LIVE | trusted=false renders نامطمئن |
| Customer preview | management_customer_preview_rows | NORMAL_CANONICAL | ACTUAL_PAID_LIVE | trusted=false renders bounded safe status |
| Satisfaction summary | management_satisfaction_summary | NORMAL_CANONICAL | satisfaction_avg source field | null/untrusted is not zero |
| Contact status summary | management_contact_status_rows | NORMAL_CANONICAL interactions | event/contact domain | null/untrusted is not zero |
| Route summary | management_route_summary_rows | NORMAL_CANONICAL | ACTUAL_PAID_LIVE | null/untrusted is not zero |
| Route detail | management_route_detail_rows | NORMAL_CANONICAL | ACTUAL_PAID_LIVE | bounded limit, null/untrusted is not zero |
| Service cases | management_service_case_summary_rows | CASE_DOMAIN | SERVICE_CASE_DOMAIN_NOT_REVENUE | null/untrusted is not zero |
| Source services | management_source_service_summary_rows | NORMAL_CANONICAL | source-service count only | null/untrusted is not zero |

## Boundaries

- No customer mutation.
- No aggregate update or cache rebuild.
- No schema change.
- No Guard change.
- No Run #7 continuation.
- No Run #8 creation.
- No CSV/Excel export migration.
- No REST reporting migration.

## Acceptance Rule

B.2.2 is acceptable only if existing B.2.1 dashboard tests remain green, B.2.2 reports tests remain green, all PHP files lint, JS syntax checks pass, and final ZIP extraction reruns the same checks.
