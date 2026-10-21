# QA-REPORT-v0.8.55.0

# 1 Package Identity

Version: 0.8.55.0

Package filename: `biawin-crm-integration-v0.8.55.0-reporting-source-of-truth-foundation.zip`

SHA256: reported in the external final response and output copy after final packaging.

# 2 Files Changed

Added:
- `includes/class-biawin-crm-reporting-population-service.php`
- `includes/class-biawin-crm-reporting-financial-service.php`
- `includes/class-biawin-crm-reporting-assignment-service.php`
- `includes/class-biawin-crm-reporting-validation-service.php`
- `tests/reporting-foundation-regression.php`
- `REPORTING-SOURCE-OF-TRUTH-v0.8.55.0.md`
- `QA-REPORT-v0.8.55.0.md`

Changed:
- `biawin-crm-integration.php`
- `admin/class-biawin-crm-admin.php`
- `includes/class-biawin-crm-activator.php`
- `CHANGELOG.md`
- `readme.txt`

# 3 Architecture

Release A adds separated read-only reporting services:

- `Biawin_CRM_Reporting_Population_Service`
- `Biawin_CRM_Reporting_Financial_Service`
- `Biawin_CRM_Reporting_Assignment_Service`
- `Biawin_CRM_Reporting_Validation_Service`

Existing dashboards and exports remain backward-compatible. Full consumer migration is deferred to Release B/C.

# 4 Population Semantics

Implemented:

- `ALL_LIVE`
- `NORMAL_CANONICAL`
- `GUARDED`
- `BLACKLISTED_LIVE`
- `UNRESOLVED`
- `NORMAL_PLUS_VISIBLE_QUARANTINE`

`GUARDED` is derived only from live Readiness Guard state.

`BLACKLISTED_LIVE` is derived from current live customers and the official blacklist file.

`NORMAL_CANONICAL` excludes Guarded rows and live blacklisted rows.

# 5 Financial Semantics

`ACTUAL_PAID_LIVE`:

`SUM(CASE WHEN COALESCE(exclude_from_total,0)=1 THEN 0 ELSE amount_paid END)`

`DECLARED_PAID_LATEST_CUSTOMER_CLAIM`:

latest non-null `interactions.declared_paid`.

`APPROVED_PAID`:

unknown / not defined in this release.

Cached fields are compared only:

- `customers.total_paid`
- `customers.declared_paid`
- `customers.discrepancy_amount`

# 6 Assignment Semantics

Canonical current workload:

newest active assignment per customer.

Duplicate active assignments are exposed as an exception metric and are not repaired.

# 7 Case Validation

Read-only orphan/reference checks are implemented for:

- complaints
- deficiency cases
- financial cases
- service cases
- refunds

The validation output includes total rows, valid references, orphan references, guarded references, and blacklisted references.

# 8 Guard / Blacklist Policy

Guarded and blacklisted rows are first-class reporting populations.

They are not silently hidden inside normal management counts.

# 9 Frozen Plan Rule

The foundation services do not depend on the Frozen Run #7 plan for business populations, financial KPIs, assignment KPIs, case KPIs, or exports.

Frozen Plan remains historical evidence only.

# 10 Validation Page

Route:

`admin.php?page=biawin-crm-reporting-sot`

Capability:

`bcrm_reports`

The page states:

READ-ONLY — NO DATA REPAIR — NO MIGRATION — NO RUN #8 — NO GUARD MUTATION

# 11 Query Inventory

New read-only SQL families:

- live customer population counts
- live Guard customer ids
- live blacklisted customer ids
- live invoice actual-paid aggregate
- cached total/declared/discrepancy freshness comparison
- newest active assignment summary
- duplicate active assignment exception count
- guarded active assignment count
- unassigned all-live customer count
- case ownership valid/orphan/guarded/blacklisted checks
- activities/audit_log duplicate-risk indicator
- migration-state evidence read

# 12 Performance Review

Queries are aggregate or batched.

Blacklist matching is chunked in batches of 500 values.

No customer-by-customer recalculation loop is introduced.

# 13 Index Review

No indexes were added.

Recommended future index review only:

- customers.source_customer_id
- readiness_quarantine.customer_id
- invoices.customer_id, exclude_from_total
- interactions.customer_id, id, declared_paid
- assignments.customer_id, status, id
- case table customer_id columns

# 14 Regression Tests

Test harness:

`tests/reporting-foundation-regression.php`

Coverage: 32 fixture/semantic assertions.

TOTAL_PASS = 32

TOTAL_FAIL = 0

# 15 Syntax

PHP lint from final packaged ZIP extraction: PASS, 29 PHP files.

JS syntax from final packaged ZIP extraction: PASS, 3 JavaScript files.

# 16 Mutation Audit

RUN #7 MUTATION = NO
RUN #7 CONTINUATION = NO
RUN #8 IMPLEMENTED = NO
GUARD MUTATION = NO
CUSTOMER MIGRATION = NO
CUSTOMERID MUTATION = NO
INVOICE MUTATION = NO
PHONE MUTATION = NO
ASSIGNMENT MUTATION = NO
CASE MUTATION = NO
DATABASE SCHEMA MUTATION = NO

# 17 Backward Compatibility

Existing dashboards, reports, exports, portal pages, workflows, and reconciliation pages remain registered.

Release A adds foundation services and a new validation page. It does not replace all existing consumers.

The `0.8.54.2 -> 0.8.55.0` upgrade path is marked code-only so the existing schema creation/upgrade routine is not re-run for this foundation release.

# 18 Known Limitations

Management reports are not yet fully migrated to the new source-of-truth services.

CSV export unification belongs to a later release.

Full expert performance reporting belongs to a later release.

Production data validation still requires a read-only production run of the new validation page.

# 19 Production Installation Guidance

Install the plugin ZIP only.

Do not run migration, repair, Run #8, Guard sync, or customer recalculation as part of this release.

After installation, open:

`CRM بیاوین -> Reporting SOT`

Review the read-only validation output.

# 20 Next Recommended Release

Release B should migrate dashboard and KPI consumers to the new services:

- Admin Dashboard
- Operational Reports Page KPI cards
- Assignment top cards
- Portal dashboard labels/metadata

Release C should migrate exports and shared CSV/XLSX hardening.
