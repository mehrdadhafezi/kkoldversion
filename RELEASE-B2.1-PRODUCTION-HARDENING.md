# BIAWIN CRM
# v0.8.57.1 - B.2.1 Production Hardening

Base package: `biawin-crm-integration-v0.8.57.0-management-dashboard-migration.zip`  
Base SHA256: `BE8FDBB495CB3350C8D04505C162EF65C1E4F8FE89533EED0C78655283653B13`  
Target version: `v0.8.57.1`  
Release name: Management Dashboard Production Hardening

## Scope

This patch fixes only the failed v0.8.57.0 release gate blockers for the main management dashboard KPI migration. It does not migrate exports, REST APIs, agent dashboards, workload pages, case detail reports, or any mutation workflow.

## Blockers Fixed

| Blocker | v0.8.57.0 risk | v0.8.57.1 hardening |
|---|---|---|
| Dashboard read load | Financial KPI could materialize all normal-canonical IDs and then batch customer snapshots. | Main dashboard uses `management_actual_paid_total()` with one database-side invoice aggregate over customers minus excluded Guard/blacklist IDs. |
| Raw diagnostics in UI | Untrusted adapter notes could append internal error codes. | Dashboard maps untrusted statuses to bounded Persian user-facing messages and never renders raw SQL/table/path diagnostics. |
| Direct upgrade path | Direct `0.8.55.2 -> 0.8.57.0` could fall through to activation. | Direct `0.8.55.2`, `0.8.56.0`, `0.8.56.1`, and `0.8.57.0` to `0.8.57.1` update metadata only and return before activation/schema paths. |
| Package hygiene | Historical reconstruction JSON artifacts were packaged under `data/`. | Production package keeps only the runtime blacklist file under `data/`; reconstruction/control JSON files are excluded from the plugin package. |

## Preserved Semantics

- Main dashboard KPI count remains exactly 12.
- Customer populations remain separated as `NORMAL_CANONICAL`, `GUARDED`, `BLACKLISTED_LIVE`, and `UNRESOLVED`.
- Financial total remains `ACTUAL_PAID_LIVE` from live invoice evidence for `NORMAL_CANONICAL`.
- Approved-paid remains unresolved and is not substituted into dashboard revenue.
- Assignment KPIs use canonical newest-active assignment semantics.
- Case KPIs use case adapter semantics and do not inflate canonical customer counts with deficiency orphans.
- No Run #8 was created and Run #7 terminal metadata was not touched.
- No customer data, aggregates, Guard state, blacklist source, or migration state is mutated.

## Performance Contract

The management financial KPI performs:

- dependency checks for customer, invoice, Guard, and blacklist sources;
- request-level memo reuse for population sources;
- one database-side aggregate over `customers` joined to `invoices`;
- exclusion materialization only for Guard and blacklist IDs, not the full normal-canonical customer population.

Query complexity classification: `MEDIUM`, because the blacklist SOT still resolves official blacklist source IDs to live customer IDs in chunks. The high-load v0.8.57.0 full population snapshot path is removed from the main dashboard financial KPI.

## Upgrade Contract

The following source versions have explicit code-only metadata gates to `0.8.57.1`:

- `0.8.55.2`
- `0.8.56.0`
- `0.8.56.1`
- `0.8.57.0`

These gates call only `update_option('biawin_crm_db_version', BIAWIN_CRM_VERSION)` and then return. They do not call `activate()`, `dbDelta()`, migrations, data rebuilds, Guard changes, or reconstruction tooling.

## Verification Summary

- `tests/reporting-foundation-regression.php`: PASS
- `tests/reporting-consumer-adapter-b1.php`: PASS
- `tests/management-dashboard-b21.php`: PASS
- `tests/management-dashboard-hardening-v08571.php`: PASS
- PHP lint: required before final package acceptance
- JavaScript syntax: required before final package acceptance
- Final ZIP extract-and-test: required before final package acceptance
