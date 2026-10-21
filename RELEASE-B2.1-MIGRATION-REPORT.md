# BIAWIN CRM
# Release B.2.1 - Main Management Dashboard Migration Report

Base: v0.8.56.1  
Target version: v0.8.57.1  
Scope: only main management dashboard KPI consumers  
Mode: implementation complete, production hardening applied, independent audit required

No customer data was modified. No aggregates were updated. No cache was rebuilt. Guard, Run #7, and Run #8 were not changed.

## Migrated KPI Consumers

| KPI | Before | After | SOT / Policy |
|---|---|---|---|
| Normal customer profiles | `SELECT COUNT(*) FROM customers`; all live rows | `customer_population_count(NORMAL_CANONICAL)` | Population SOT; Guarded and Blacklisted excluded from business total |
| Guarded profiles | not visible as a separated dashboard KPI | `customer_population_count(GUARDED)` | Population SOT; Guard visibility preserved |
| Blacklisted live profiles | not visible as a separated dashboard KPI | `customer_population_count(BLACKLISTED_LIVE)` | Population SOT; official blacklist source |
| Unresolved profiles | hidden inside raw profile count | `customer_population_count(UNRESOLVED)` | Population SOT; unresolved/Guard rows visible separately |
| Actual paid | legacy dashboard showed raw invoice count from `invoices`; legacy reports use cached customer totals elsewhere | `management_actual_paid_total()` | Financial SOT; `ACTUAL_PAID_LIVE`; database-side aggregate over `NORMAL_CANONICAL`; no `customers.total_paid` dependency |
| Workable active assignment | not represented on main dashboard | `workable_active_workload()` | Assignment SOT; newest active assignment minus Guard workload |
| Unassigned normal canonical | not represented on main dashboard | `unassigned_normal_canonical()` | Assignment SOT; normal canonical population only |
| Complaint cases | not represented on main dashboard | `case_population_summary(complaints)` | Case adapter; orphan references excluded from trusted value |
| Deficiency cases | legacy suspicious merge card used `customers.status='needs_review'` | `case_population_summary(deficiency_cases)` | Case adapter; case-local amount semantics only |
| Financial cases | not represented on main dashboard | `case_population_summary(financial_cases)` | Case adapter; financial case-local amounts |
| Service cases | not represented on main dashboard | `case_population_summary(service_cases)` | Case adapter; service credit is not revenue |
| Refund cases | not represented on main dashboard | `case_population_summary(refunds)` | Case adapter; approved amount remains case-local |

## Dependencies Removed From Main Dashboard

- Removed raw `global $wpdb` access from `Biawin_CRM_Admin::dashboard`.
- Removed direct `COUNT(*) FROM customers`.
- Removed direct `COUNT(*) FROM invoices`.
- Removed direct `COUNT(*) FROM customers WHERE status='needs_review'`.
- Main dashboard does not reference `customers.total_paid`, `declared_paid`, or cached financial aggregate fields.

## Guard Visibility

The dashboard now displays `NORMAL_CANONICAL`, `GUARDED`, `BLACKLISTED_LIVE`, and `UNRESOLVED` as separate cards. If Guard or blacklist dependencies are unavailable, the adapter result is rendered as untrusted instead of converting unknown state into zero.

## v0.8.57.1 Production Hardening Addendum

- Replaced the v0.8.57.0 dashboard financial path with `management_actual_paid_total()` so the dashboard no longer materializes the full normal-canonical population before summing live invoice evidence.
- Preserved the filtered public `financial_total_for_population()` behavior; only unfiltered `NORMAL_CANONICAL` totals use the database-side management aggregate shortcut.
- Added bounded Persian dashboard messages for untrusted adapter states. Raw SQL, `$wpdb`, table, column, and filesystem diagnostic strings remain internal and are not rendered to management users.
- Added code-only upgrade metadata gates for direct `0.8.55.2`, `0.8.56.0`, `0.8.56.1`, and `0.8.57.0` installs upgrading to `0.8.57.1`.
- Removed historical/manual reconstruction JSON artifacts from the Production plugin package. `data/blacklisted-customer-ids.txt` remains because it is a runtime source for the official blacklist SOT.

## B.2 Scope Not Migrated

- CSV exports
- Excel exports
- REST APIs
- Agent dashboards
- Workload pages and assignment mutation flows
- Case detail reports
- Reports page, Data Audit page, and Reporting SOT page

## Test Coverage

- `tests/management-dashboard-b21.php` covers KPI parity, population separation, financial source, Guard visibility, scope fail-closed behavior, no mutation tokens, and out-of-scope surfaces.
- `tests/management-dashboard-hardening-v08571.php` renders the dashboard under trusted, failed-financial, and ordinary-agent scopes and verifies bounded safe messages plus 12 KPI cards.
- `tests/reporting-consumer-adapter-b1.php` remains active with its consumer migration expectation updated for B.2.1.
