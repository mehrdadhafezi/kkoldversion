# Reporting Source-of-Truth Foundation v0.8.55.0

This document defines the first shared reporting truth layer for Biawin CRM after Run #7.

## Operating Rule

v0.8.55.0 is foundation-only.

- Run #7 continuation: NO
- Run #8 implementation: NO
- Guard mutation: NO
- Customer migration: NO
- CustomerID mutation: NO
- Invoice/phone/assignment/case ownership mutation: NO
- Database schema mutation: NO

## Population Semantics

`ALL_LIVE`
- Every row in the current live `customers` table.
- Technical population only.
- It must not automatically be labeled as the canonical management customer count.

`GUARDED`
- Current live customers present in the Readiness Guard / quarantine table.
- Derived from live Guard state only.
- Never derived from the Frozen Run #7 plan.

`BLACKLISTED_LIVE`
- Current live customers whose `source_customer_id` is in the official blacklist file.
- Reported independently from Guard because Guard and blacklist may diverge.

`UNRESOLVED`
- Current unresolved identity population.
- In v0.8.55.0 this maps to live Guard rows.

`NORMAL_CANONICAL`
- Normal future business-reporting population.
- Excludes live Guarded rows and live blacklisted CustomerIDs.
- Does not infer identity from phone number.
- Does not use Frozen Plan ownership as live identity.

`NORMAL_PLUS_VISIBLE_QUARANTINE`
- Management display population that keeps normal and quarantine visible as separate segments.

## Financial Semantics

`ACTUAL_PAID_LIVE`
- Source: live `invoices.amount_paid`.
- Formula: sum invoice amount while treating `exclude_from_total=1` as zero.
- Zero-invoice customers resolve to zero through `COALESCE`.

`DECLARED_PAID_LATEST_CUSTOMER_CLAIM`
- Source: latest non-null `interactions.declared_paid` for the customer.
- It is a customer claim, not verified actual payment.

`APPROVED_PAID`
- Not defined in this release because no single authoritative approved-payment source was proven by the audit.

Cached fields:
- `customers.total_paid`
- `customers.declared_paid`
- `customers.discrepancy_amount`

These fields remain available for backward compatibility and display speed, but v0.8.55.0 treats them as cached/denormalized values that must be compared with live reporting truth before management reliance.

## Assignment Semantics

Current workload source-of-truth:

`NEWEST ACTIVE ASSIGNMENT PER CUSTOMER`

The canonical active assignment query is:

`status='assigned' AND id=(SELECT MAX(id) FROM assignments WHERE same customer_id AND status='assigned')`

Duplicate active assignments are not repaired by this release. They are reported as a data-quality exception.

## Case Ownership Semantics

The validation layer checks these customer ownership references:

- `complaints.customer_id`
- `deficiency_cases.customer_id`
- `financial_cases.customer_id`
- `service_cases.customer_id`
- `refunds.customer_id`

For each domain it reports total rows, valid references, orphan references, guarded references, and blacklisted references.

## Guard / Blacklist Policy

Guarded rows must never silently disappear from management reporting.

Future management dashboards should show:

- normal canonical count
- guarded count
- blacklisted live count
- unresolved count

Future KPIs that use `NORMAL_CANONICAL` must label that population explicitly.

## Frozen Run #7 Rule

The Frozen Run #7 plan is historical evidence only.

It may be used for:

- reconciliation provenance
- audit evidence
- explaining previous decisions

It must not feed:

- management total customer count
- revenue KPIs
- assignment KPIs
- case KPIs
- expert performance KPIs
- business exports

## Expert Event Attribution Contract

Full expert reporting is not implemented in Release A.

The contract is:

- `interactions.agent_user_id` is contact actor evidence.
- `activities.actor_user_id` is operational timeline actor evidence.
- `assignments.agent_user_id` and `assignments.assigned_by` are assignment actor evidence.
- `case_actions.user_id` is workflow actor evidence.
- `audit_log` is audit evidence and must not be automatically added to operational event totals.

Activities and audit_log may describe the same business action, so future performance reporting needs dedupe rules.

## Validation Page

Route:

`admin.php?page=biawin-crm-reporting-sot`

Capability:

`bcrm_reports`

The page is read-only and exposes:

- Population Summary
- Financial Freshness
- Assignment Integrity
- Case Ownership Integrity
- Migration State
- Event Double-Count Risk
- Source metadata

No repair button is exposed.

## Index Recommendations

No indexes are added in this release.

Recommended future review:

- `customers.source_customer_id`
- `readiness_quarantine.customer_id`
- `invoices.customer_id, exclude_from_total`
- `interactions.customer_id, id, declared_paid`
- `assignments.customer_id, status, id`
- `complaints.customer_id`
- `deficiency_cases.customer_id`
- `financial_cases.customer_id`
- `service_cases.customer_id`
- `refunds.customer_id`
- `activities.customer_id, type, created_at`
- `audit_log.object_type, object_id, action, created_at`

## Release Boundaries

Release B should migrate selected dashboard/KPI consumers to these services.

Release C should unify exports and CSV hardening.

Release D should implement expert event reporting and case reporting consistency.
