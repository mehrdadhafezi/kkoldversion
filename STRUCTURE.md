# Biawin CRM Integration — Structure & Architecture Baseline

> Baseline: **v0.8.65.17**  
> Scope: documentation-only description of the current plugin architecture.  
> Evidence class: **CODE-INFERRED** from the packaged source; this document is not a runtime/staging proof.  
> Runtime code is not changed by this documentation package.

## Purpose

This file is the entry point for understanding the plugin before changing workflow behavior. The most sensitive area is the **Deficiency ↔ Finance** workflow because state, assignment, permissions, receipt review progress, customer finance data, and final completion currently interact across several services.

For the full state machine, transitions, role gates, and known inconsistencies, see:

- [`docs/DEFICIENCY-FINANCE-STATE-MACHINE.md`](docs/DEFICIENCY-FINANCE-STATE-MACHINE.md)

## Main runtime components relevant to Deficiency / Finance

| Component | Responsibility |
|---|---|
| `includes/class-biawin-crm-deficiency-workflow.php` | Deficiency/finance case state machine, assignment, contact/result actions, send-to-finance, finance-return follow-up, returned invoice, corrected declared amount, final decision, timeline/action events, tab queries |
| `includes/class-biawin-crm-finance-review.php` | Per-receipt finance review, review status/details, finance review progress, final return to deficiency |
| `includes/class-biawin-crm-receipt-staging.php` | Receipt staging/upload flow before final case persistence |
| `includes/class-biawin-crm-customer-service.php` | Customer/invoice-side updates used by workflow actions, including manual invoice creation |
| `includes/class-biawin-crm-workflow-service.php` | Shared workflow settings and customer reconciliation context |
| `includes/class-biawin-crm-activator.php` | Roles/capabilities and compatibility repairs/migrations |
| `includes/class-biawin-crm-frontend.php` | Frontend/customer profile surfaces that consume workflow/customer data |
| `admin/class-biawin-crm-admin.php` | Admin routes/pages and management entry points |

## Core persistence used by this workflow

| Table | Role in workflow |
|---|---|
| `bcrm_deficiency_cases` | Canonical case state, department, assignees, amounts, finance timestamps/results, final decision |
| `bcrm_case_actions` | Workflow transition/action trail |
| `bcrm_attachments` | Case evidence; bank receipts use `object_type='deficiency_case'` and `file_kind='bank_receipt'` |
| `bcrm_receipt_reviews` | Per-receipt finance decision (`pending/approved/rejected/refunded`) and details |
| `bcrm_customers` | Customer declared/registered totals and profile completion state |
| `bcrm_invoices` / invoice-related storage | Financial invoice truth used by reconciliation/customer totals |
| `bcrm_activities` / `bcrm_audit_log` | Additional operational/audit history where applicable |

## Architectural model

The current implementation combines three related but different concepts:

1. **Workflow state** — where the case is in the business process.
2. **Assignment state** — which deficiency/finance user owns the case.
3. **Receipt review progress** — how many bank receipts have been individually decided by finance.

These concepts must not be treated as interchangeable. For example, reassigning a case normally preserves its workflow state, while finance progress is computed from `attachments` + `receipt_reviews` rather than solely from a case status.

## Current state families

### Active deficiency states

- `INCOMPLETE_NEW`
- `INCOMPLETE_ASSIGNED`
- `CUSTOMER_CONTACT_PENDING`
- `DEFICIENCY_UNDER_REVIEW`
- `RETURNED_TO_DEFICIENCY`
- `READY_FOR_FINAL_DECISION`

### Active finance states

- `FINANCE_REVIEW_PENDING`
- `FINANCE_ASSIGNED`
- `FINANCE_UNDER_REVIEW`

### Legacy/compatibility finance states still declared

- `FINANCE_APPROVED_WITH_INVOICE`
- `FINANCE_APPROVED_WITHOUT_INVOICE`
- `FINANCE_REJECTED`

These remain in `OPEN_STATUSES`/labels, but the current per-receipt finance finalization path does not use them as the terminal finance case state; it returns the case to `RETURNED_TO_DEFICIENCY`.

### Closed states

- `COMPLETED`
- `ARCHIVED`

## Change-safety rule

Before adding or changing a workflow feature, define the change in this form:

```text
Actor
Current State
Action
Guard / Permission
Data Changed
Next State
Ownership / Assignment Effect
Audit / Timeline Event
Side Effects
```

Do not implement a new action only by placing a button in a tab. The backend transition, role/capability gate, assignment ownership, persistence, and audit event must agree.

## Known architectural risks / inconsistencies

The detailed document contains evidence and behavior, but the current baseline has these notable risks:

1. **Two finance completion contracts exist**: the current per-receipt flow and a legacy case-level `record_finance_result()` path.
2. **`no_answer` differs by action path**: contact registration and finance-return follow-up can transition to different states.
3. **Manual assignment and upload assignment are not fully aligned** for `RETURNED_TO_DEFICIENCY`.
4. **Return-assignment is more restrictive than reassignment**; it only handles initial assigned states.
5. **`RETURNED_TO_DEFICIENCY` is a hub state** with many possible actions, including final decision.
6. Legacy finance status constants remain present although the primary finance UI is receipt-driven.

These are documentation findings, not automatically approved refactoring tasks.

## Documentation maintenance rule

Whenever workflow behavior changes, update both:

- this architecture index if component responsibility or invariants change;
- `docs/DEFICIENCY-FINANCE-STATE-MACHINE.md` if a state, transition, permission, assignment rule, receipt-review rule, tab mapping, or known inconsistency changes.

## Ownership Reconciliation — proposed future domain

A separate **Ownership Reconciliation / تطبیق مالکیت** domain has been designed for disputes over locked completed-profile invoices and phone ownership. It is intentionally **not** part of the current Deficiency state machine and is **not implemented in v0.8.65.17**.

The approved/proposed architecture contract is documented at:

- [`docs/OWNERSHIP-RECONCILIATION-ARCHITECTURE-CONTRACT.md`](docs/OWNERSHIP-RECONCILIATION-ARCHITECTURE-CONTRACT.md)

Key boundaries:

- claim submission must not mutate canonical ownership;
- locked invoice/phone ownership changes must go through a dedicated ownership case and backend guard;
- invoice ownership, phone ownership, and invoice-phone binding are distinct concepts;
- every invoice must ultimately have exactly one current phone under the confirmed target business rule;
- ownership review uses its own hold/state/audit model rather than overwriting Deficiency/Finance states;
- approved transfers trigger customer recalculation and may route the old profile back into normal Deficiency if it becomes incomplete;
- the new domain should be implemented in dedicated ownership services/classes, not embedded into `class-biawin-crm-deficiency-workflow.php`.

## Ownership Reconciliation implementation design

The approved domain contract is implemented from `docs/OWNERSHIP-RECONCILIATION-ARCHITECTURE-CONTRACT.md`; exact proposed schema/migrations/class boundaries are frozen in `docs/OWNERSHIP-RECONCILIATION-IMPLEMENTATION-DESIGN.md`. Both are documentation only in baseline 0.8.65.17; runtime code is not yet implemented.

## Ownership Reconciliation C2 — backend implementation (0.8.65.19)

The ownership backend now lives in dedicated repository/workflow/risk/OTP/transfer/audit services, plus Customer Hold, Ownership Lock, Invoice Phone and legacy Write Guard services. See `docs/OWNERSHIP-RECONCILIATION-C2-REPORT.md` for the callable API, verification limits and deployment prerequisites. No ownership UI is implemented.

Existing CRM writes delegate to `Biawin_CRM_DB::write()` for centralized hold/ownership-lock checks. Customer mutation entry points and receipt promotion also validate holds before side effects. Ordinary reads stay available. Completed-profile recalculation ensures persistent invoice locks only where the current phone is deterministic; ambiguous legacy records produce audit findings. Deficiency/Finance state constants and transition logic are preserved; approved ownership finalization invokes their existing recalculation/routing paths under a private ownership execution context.

## Ownership Reconciliation C3 — UI (on 0.8.65.19)

The ownership UI is a presentation/transport layer: `Biawin_CRM_Ownership_Presenter` (read-only view models from `Workflow::get()/list_cases()`), `Biawin_CRM_Ownership_Ajax` (one `wp_ajax_bcrm_ownership` action whose ops each call a single approved backend command) and `Biawin_CRM_Ownership_UI` (workspaces). Assets live in `ownership-ui/` because `assets/`, the admin controller and the frontend controller are frozen by the C2 regression suite; the portal view `bcrm_view=ownership` is attached through shortcode filters and the admin page as a `biawin-crm` submenu. See `docs/OWNERSHIP-RECONCILIATION-C3-UI.md`. New ownership actions must still be added as backend commands first; the UI never mutates ownership tables.

## Portal presentation layer — C3.11

`Biawin_CRM_Portal_UI` only enqueues `portal-ui/portal.css` and `portal-ui/portal.js` after the frozen portal assets (priority 40, logged-in `bcrm_work_cases` users). It is progressive enhancement over the frozen shell: no server calls, no markup changes on the server, no routes or permissions. `tests/browser/portal-review.js` sweeps every portal view at six widths. See `docs/PORTAL-UI-UX-REVIEW-C3-11.md`.
