# Deficiency + Finance State Machine — v0.8.65.17

> Status: **current source baseline documentation**  
> Baseline plugin: **Biawin CRM Integration v0.8.65.17**  
> Evidence: **CODE-INFERRED** from packaged source, not runtime/staging verification.  
> This document records current behavior; it does not approve refactoring or business-rule changes.

## 1. Canonical workflow states

`Biawin_CRM_Deficiency_Workflow::OPEN_STATUSES` currently declares:

| State | Current label | Operational role |
|---|---|---|
| `INCOMPLETE_NEW` | ناقص جدید | New deficiency case, not yet initially assigned |
| `INCOMPLETE_ASSIGNED` | تخصیص رفع ناقصی | Deficiency case initially assigned |
| `CUSTOMER_CONTACT_PENDING` | منتظر تماس مشتری | Waiting for customer/contact follow-up |
| `DEFICIENCY_UNDER_REVIEW` | در حال بررسی رفع ناقصی | Deficiency work in progress |
| `FINANCE_REVIEW_PENDING` | ارسال‌شده به مالی | Sent to finance, waiting for finance assignment/review |
| `FINANCE_ASSIGNED` | تخصیص مالی | Assigned to finance reviewer |
| `FINANCE_UNDER_REVIEW` | در حال بررسی مالی | At least finance review work has begun |
| `FINANCE_APPROVED_WITH_INVOICE` | تأیید مالی با فاکتور | Legacy/compatibility declared finance state |
| `FINANCE_APPROVED_WITHOUT_INVOICE` | تأیید مالی بدون فاکتور | Legacy/compatibility declared finance state |
| `FINANCE_REJECTED` | عدم تأیید مالی | Legacy/compatibility declared finance state |
| `RETURNED_TO_DEFICIENCY` | برگشتی از مالی | Finance completed and case returned to deficiency |
| `READY_FOR_FINAL_DECISION` | آماده تصمیم نهایی | Deficiency work resolved enough for final decision |

Closed states:

- `COMPLETED`
- `ARCHIVED`

### Important interpretation

The current **per-receipt finance review flow** stores approval/rejection/refund on each receipt in `bcrm_receipt_reviews` and then returns the case to `RETURNED_TO_DEFICIENCY`. Therefore `FINANCE_APPROVED_WITH_INVOICE`, `FINANCE_APPROVED_WITHOUT_INVOICE`, and `FINANCE_REJECTED` are still declared but are not the primary terminal case states of the current receipt-driven UI.

---

## 2. High-level state map

```text
INCOMPLETE_NEW
    |
    | deficiency assignment
    v
INCOMPLETE_ASSIGNED
    |
    +------ contact / review actions -------------------+
    |                                                   |
    v                                                   v
CUSTOMER_CONTACT_PENDING                      DEFICIENCY_UNDER_REVIEW
    |                                                   |
    +----------------- further action ------------------+
                                                        |
                                      +-----------------+------------------+
                                      |                                    |
                                      v                                    v
                           READY_FOR_FINAL_DECISION              FINANCE_REVIEW_PENDING
                                      |                                    |
                                      |                               finance assign
                                      |                                    v
                                      |                             FINANCE_ASSIGNED
                                      |                                    |
                                      |                            receipt review starts
                                      |                                    v
                                      |                          FINANCE_UNDER_REVIEW
                                      |                                    |
                                      |                           all receipts decided
                                      |                                    v
                                      +---------------------- RETURNED_TO_DEFICIENCY
                                                                    |
                                             +----------------------+------------------+
                                             |                      |                  |
                                             v                      v                  v
                                 DEFICIENCY_UNDER_REVIEW  CUSTOMER_CONTACT_PENDING  READY_FOR_FINAL_DECISION
                                             |                      |                  |
                                             +--- send more receipts ------------------+
                                                                    |
                                                                    v
                                                         FINANCE_REVIEW_PENDING

READY_FOR_FINAL_DECISION -> COMPLETED / ARCHIVED
RETURNED_TO_DEFICIENCY   -> may also reach final decision paths under current rules
```

---

## 3. Case creation and tolerance gate

A deficiency case is normally created as:

```text
status = INCOMPLETE_NEW
current_department = deficiency
```

However, `ensure_case_for_customer()` first checks the financial difference against the configured reconciliation tolerance. The effective tolerance is at least `500000` and may be higher based on workflow settings. If an existing open case becomes within tolerance, the workflow can auto-complete it instead of creating/keeping another active case.

Implication: entry into deficiency is not only a UI choice; there is a financial reconciliation gate.

---

## 4. Deficiency assignment model

### 4.1 Direct/manual assignment

For deficiency, `assign_cases()` currently allows:

- `INCOMPLETE_NEW`
- `INCOMPLETE_ASSIGNED`
- `DEFICIENCY_UNDER_REVIEW`
- `CUSTOMER_CONTACT_PENDING`
- `READY_FOR_FINAL_DECISION`
- `RETURNED_TO_DEFICIENCY`

Behavior:

- Initial assignment of `INCOMPLETE_NEW` transitions to `INCOMPLETE_ASSIGNED`.
- Reassignment of the other allowed states **preserves the current workflow state**.
- `RETURNED_TO_DEFICIENCY` can therefore be assigned/reassigned independently without being reset to `INCOMPLETE_ASSIGNED`.

### 4.2 Finance assignment

Finance assignment accepts `FINANCE_REVIEW_PENDING` and transitions:

```text
FINANCE_REVIEW_PENDING -> FINANCE_ASSIGNED
```

while setting the finance assignee/timestamp.

### 4.3 Assignment import inconsistency

The upload/import assignment path uses a narrower deficiency eligibility query:

```text
INCOMPLETE_NEW
INCOMPLETE_ASSIGNED
DEFICIENCY_UNDER_REVIEW
CUSTOMER_CONTACT_PENDING
READY_FOR_FINAL_DECISION
```

`RETURNED_TO_DEFICIENCY` is **not included** there.

Therefore current source behavior is:

| Operation | `RETURNED_TO_DEFICIENCY` |
|---|---:|
| Manual assignment | Yes |
| Reassignment in deficiency UI | Yes |
| Assignment from returned-finance tab | Yes |
| Assignment import/upload | No |

This is a known source inconsistency, not an approved business rule.

### 4.4 Return-assignment is not reassignment

`return_assignments()` accepts only:

- deficiency: `INCOMPLETE_ASSIGNED`
- finance: `FINANCE_ASSIGNED`

and returns them to:

- `INCOMPLETE_NEW`
- `FINANCE_REVIEW_PENDING`

respectively.

Thus a case in `CUSTOMER_CONTACT_PENDING`, `DEFICIENCY_UNDER_REVIEW`, `READY_FOR_FINAL_DECISION`, or `RETURNED_TO_DEFICIENCY` can be reassigned where allowed, but cannot use the same "return assignment" mechanism to clear ownership.

---

## 5. Action dispatcher

The main deficiency workflow currently routes actions including:

- `contact`
- `result`
- `send_finance`
- `return_invoice`
- `finance_return_followup`
- `complete_corrected`
- `complete`
- `archive`

Closed cases (`COMPLETED` / `ARCHIVED`) are rejected for normal modification.

---

## 6. Initial action — contact registration

`record_contact()` requires deficiency processing/management access.

Current contact outcomes include the pre-existing items such as:

- عدم پاسخ (`no_answer`)
- پیگیری مجدد (`follow_up`)
- در انتظار ارسال فیش (`awaiting_receipt`)
- and the other configured contact outcomes shown by the current form.

Current transition rule inside `record_contact()`:

```text
follow_up / awaiting_receipt -> CUSTOMER_CONTACT_PENDING
all other accepted contact outcomes -> DEFICIENCY_UNDER_REVIEW
```

### Known semantic mismatch: `no_answer`

Under **contact registration**, `no_answer` falls into the general branch and therefore moves to:

```text
DEFICIENCY_UNDER_REVIEW
```

Under **finance-return follow-up** (section 8), `no_answer` moves to:

```text
CUSTOMER_CONTACT_PENDING
```

This difference exists in the current source and should be treated as a business-decision gap before refactoring.

---

## 7. Deficiency result registration

The current result options include:

- `resolved`
- `real_mismatch`
- `extra_payment_claim`
- `missing_invoice`
- `incomplete_invoice`
- `needs_finance`
- `needs_more_review`
- `other`

Transition rule:

```text
resolved -> READY_FOR_FINAL_DECISION
other results -> DEFICIENCY_UNDER_REVIEW
```

The UI may suppress a duplicate `extra_payment_claim` option when the separate follow-up action is available, but the backend/result vocabulary still exists.

---

## 8. "ثبت پیگیری" action

### 8.1 Availability

`finance_return_followup` is currently available for all active deficiency states below, not only returned-finance cases:

- `INCOMPLETE_NEW`
- `INCOMPLETE_ASSIGNED`
- `DEFICIENCY_UNDER_REVIEW`
- `CUSTOMER_CONTACT_PENDING`
- `READY_FOR_FINAL_DECISION`
- `RETURNED_TO_DEFICIENCY`

and requires:

```text
current_department = deficiency
```

### 8.2 Permission logic

The action is allowed if the current user has any of:

- `bcrm_manage_cases`
- `manage_deficiency_cases`
- `process_finance_return_followup`

There is also a backward-compatibility fallback: an assigned deficiency agent with `process_deficiency_case` may use the action even if the newly-added follow-up capability is missing from an older installation.

Default role definitions currently include `process_finance_return_followup` for both:

- deficiency supervisor
- deficiency agent

### 8.3 Five follow-up sub-statuses

#### A. `approved_amount_agreed` — توافق مبلغ تایید شده

Transition:

```text
active deficiency state -> READY_FOR_FINAL_DECISION
```

and sets:

```text
deficiency_result = finance_approved_amount_agreed
```

#### B. `extra_payment_claim` — ادعای واریزی بیشتر

Transition:

```text
active deficiency state -> DEFICIENCY_UNDER_REVIEW
```

If provided, the new customer-claimed amount is persisted.

#### C. `no_answer` — عدم پاسخ

Transition:

```text
active deficiency state -> CUSTOMER_CONTACT_PENDING
```

and sets:

```text
last_contact_outcome = no_answer
```

#### D. `awaiting_receipt` — در انتظار ارسال فیش مجدد

Transition:

```text
active deficiency state -> CUSTOMER_CONTACT_PENDING
```

and sets:

```text
last_contact_outcome = awaiting_receipt
```

#### E. `send_more_receipts` — ارسال به مالی و آپلود ادامه فیش‌ها

Requirements:

- at least one new staged/uploaded receipt must be supplied;
- the request is internally marked as a finance-return follow-up so the normal returned-finance direct-send block can be bypassed deliberately.

Transition:

```text
active deficiency state -> FINANCE_REVIEW_PENDING
current_department = finance
```

The implementation reuses the normal `send_to_finance()` path rather than creating a separate finance pipeline.

---

## 9. Sending to finance

### 9.1 Allowed states

The normal send-to-finance backend accepts:

- `DEFICIENCY_UNDER_REVIEW`
- `INCOMPLETE_ASSIGNED`
- `CUSTOMER_CONTACT_PENDING`
- `READY_FOR_FINAL_DECISION`
- `RETURNED_TO_DEFICIENCY`

There is also compatibility handling for a stale `INCOMPLETE_NEW` detail page after assignment refresh.

### 9.2 Returned-finance special rule

A `RETURNED_TO_DEFICIENCY` case cannot use the generic direct send path unless the request carries the internal follow-up marker. Operationally, returned-finance re-send should therefore go through:

```text
ثبت پیگیری -> ارسال به مالی و آپلود ادامه فیش‌ها
```

### 9.3 Receipt requirement

A finance send requires bank-receipt evidence. Receipt amount mismatch versus the declared/customer amount does not by itself block the handoff; the mismatch can be logged/analyzed while the case still proceeds to finance.

### 9.4 Resulting state

```text
... -> FINANCE_REVIEW_PENDING
current_department = finance
```

---

## 10. Finance receipt review model

The current primary finance model is **per receipt**.

`bcrm_receipt_reviews.review_status` supports:

- `pending`
- `approved`
- `rejected`
- `refunded`

A review row can also store:

- `review_type`
- `reason_key`
- `approved_amount`
- `review_note`
- `details_json`
- reviewer/timestamp

Typical review types include:

- approved with invoice (`with_invoice`)
- approved without invoice (`without_invoice`)
- refunded registered/unregistered variants used by the current UI/data model

Invoice-related review details can include invoice number/customer phone and multi-link structures where supported by the current code.

---

## 11. Finance progress and finalization

Finance progress is not derived only from a case status. The finance-review code recomputes:

```text
receipt_count = bank receipts attached to the deficiency case
reviewed_count = distinct receipts with review_status in approved/rejected/refunded
```

Before finance can finalize:

- at least one bank receipt must exist;
- **all** bank receipts must have a final receipt decision.

When finance finalization succeeds:

```text
status = RETURNED_TO_DEFICIENCY
current_department = deficiency
finance_finished_at = now
```

and the workflow emits `FINANCE_RETURNED_TO_DEFICIENCY` with a summary of receipt decisions.

The per-receipt finalization path intentionally does **not** create an extra case-level "final finance approval" state.

---

## 12. Legacy case-level finance result path

`record_finance_result()` remains in the codebase for backward compatibility and accepts case-level results:

- `with_invoice`
- `without_invoice`
- `rejected`

It also returns the case to `RETURNED_TO_DEFICIENCY`, but unlike the current per-receipt finalizer it may run `maybe_auto_complete_after_finance()` for approved results. If the reconciled total falls within tolerance, it can transition:

```text
RETURNED_TO_DEFICIENCY -> COMPLETED
```

This means two finance completion contracts coexist:

### Primary/current UI contract

```text
per-receipt decisions
-> all receipts decided
-> RETURNED_TO_DEFICIENCY
```

### Legacy compatibility contract

```text
case-level finance result
-> RETURNED_TO_DEFICIENCY
-> possible automatic COMPLETED when reconciled
```

This is a major architectural debt/decision point. The existence of the legacy endpoint should not be interpreted as approval to keep both models indefinitely.

---

## 13. Returned-finance case as a hub state

A case in `RETURNED_TO_DEFICIENCY` can currently participate in several deficiency actions (subject to permission/state guards), including:

- assignment / reassignment
- contact registration
- deficiency result registration
- follow-up registration
- returned-invoice registration
- corrected declared amount
- re-send to finance via the specific follow-up path
- final completion/archive paths

Because this state has many exits, future features must define exactly which transition they intend instead of treating "returned from finance" as a passive label.

---

## 14. Returned invoice registration

`record_returned_invoice()` is restricted to:

```text
status = RETURNED_TO_DEFICIENCY
```

and deficiency process/manage permissions.

It creates the manual invoice through customer service, then recomputes declared vs registered totals.

Transition:

```text
abs(difference) <= tolerance -> READY_FOR_FINAL_DECISION
otherwise -> DEFICIENCY_UNDER_REVIEW
```

The effective tolerance is at least `500000` or the configured reconciliation tolerance if higher.

---

## 15. Corrected declared amount

The corrected-declared-amount action updates the declared/customer financial data and recalculates the profile.

Current behavior distinguishes exact/effectively exact equality from general tolerance-based reconciliation. If the corrected declared amount matches the registered invoice total closely enough for this action's equality check, the case can be completed; otherwise the case remains in its current workflow state and an action event is recorded.

This behavior is different from the general tolerance gate and should not be assumed equivalent.

---

## 16. Final decision

The current final-decision backend supports completion/archive from the states allowed by its guards, including the workflow's ready/returned-finance paths.

Outputs:

```text
complete -> COMPLETED
archive  -> ARCHIVED
```

Important design question: because `RETURNED_TO_DEFICIENCY` can reach final decision under current behavior, the system does not always require an explicit intermediate `READY_FOR_FINAL_DECISION` transition.

---

## 17. Deficiency tab mapping

The current tab query mapping is:

| Tab concept | State filter |
|---|---|
| New | `INCOMPLETE_NEW` |
| Assigned | `INCOMPLETE_ASSIGNED` |
| Under review | `DEFICIENCY_UNDER_REVIEW` |
| Waiting customer | `CUSTOMER_CONTACT_PENDING` |
| Sent to finance | `FINANCE_REVIEW_PENDING`, `FINANCE_ASSIGNED`, `FINANCE_UNDER_REVIEW` |
| Returned from finance | `RETURNED_TO_DEFICIENCY` |
| Ready for final decision | `READY_FOR_FINAL_DECISION` |
| Completed | `COMPLETED` |
| Archived | `ARCHIVED` |

The default fallback query selects non-closed cases.

---

## 18. Finance tab/progress model

Finance views combine finance state/assignee and receipt-review progress rather than relying on a single case status.

Conceptually:

- waiting allocation: finance case without finance assignee;
- assigned: finance assignee exists but no completed receipt review yet;
- under review: some but not all receipts decided;
- review complete: all receipts have final decisions;
- returned to deficiency: `RETURNED_TO_DEFICIENCY` with finance completion timestamp.

This separation is intentional and should be preserved unless a new explicit design replaces it.

---

## 19. Access / ownership model

### Deficiency supervisor / manager

Default deficiency supervisor role includes management/assignment/process/send/final-decision/report capabilities plus the follow-up capability.

### Deficiency agent

Default deficiency agent includes operational deficiency processing/send-to-finance/follow-up capabilities. Case access is additionally scoped by assignment in the workflow access checks.

### Finance supervisor

Default role includes finance management, assignment, review, approve/reject/return, and reporting capabilities.

### Finance agent

Default role includes finance review/approve/reject/return capabilities. Case access is scoped by finance assignment where applicable.

### Follow-up compatibility rule

`process_finance_return_followup` is assigned to both deficiency supervisor and deficiency agent roles, and there is an additional assigned-agent fallback using the older `process_deficiency_case` capability for installations upgraded from older versions.

---

## 20. Receipt deletion authority

The deficiency-side receipt deletion backend requires management-level capability (`manage_deficiency_cases` / `bcrm_manage_cases`), so a normal deficiency agent should not be able to delete receipts merely because the case is assigned to them.

This matches the current business requirement that receipt deletion is a supervisor/manager operation.

---

## 21. Key persistence / source-of-truth rules

### Case state and assignment

Canonical source: `bcrm_deficiency_cases`.

### Bank receipt existence

Canonical case evidence:

```text
bcrm_attachments
object_type = deficiency_case
file_kind = bank_receipt
object_id = case_id
```

### Finance decision per receipt

Canonical source: `bcrm_receipt_reviews` keyed to receipt attachment/case.

### Workflow history

`bcrm_case_actions` is the direct case action/transition trail; other activity/audit tables may also receive operational records depending on the action.

### Customer/invoice financial truth

Customer/invoice services and canonical customer/invoice tables are used for registered/declared totals and reconciliation. A receipt review decision is not by itself equivalent to rewriting all customer financial truth.

---

## 22. Known architecture/debt register

These are findings from the current source, not approved fixes:

| ID | Finding | Risk |
|---|---|---|
| DF-01 | Current per-receipt finance finalizer coexists with legacy case-level `record_finance_result()` | Two different completion semantics / possible regression |
| DF-02 | `no_answer` transitions differently in `record_contact()` vs `finance_return_followup` | Same business wording can produce different workflow state |
| DF-03 | `RETURNED_TO_DEFICIENCY` is manually assignable but omitted from assignment import eligibility | Assignment channels disagree |
| DF-04 | Reassignment supports several active deficiency states, but return-assignment only supports `INCOMPLETE_ASSIGNED` | Ownership operations are not symmetric |
| DF-05 | `RETURNED_TO_DEFICIENCY` has many exits and can act as a hub/final-decision source | Future features can accidentally bypass intended sequence |
| DF-06 | Legacy finance approval/rejection states remain declared although primary UI is receipt-driven | State vocabulary is larger than active primary workflow |
| DF-07 | Workflow, assignment, permissions, rendering, reporting/tab logic, and finance handoff are concentrated in a very large workflow class | High regression risk for patch-style changes |

---

## 23. Change-impact template

Every future workflow change should be documented and reviewed with this template before coding:

| Field | Required question |
|---|---|
| Actor | Which role/user performs it? |
| Current State | From which exact case state(s)? |
| Department | Must `current_department` be deficiency or finance? |
| Action | What business command is executed? |
| Guard | Which capability, assignment scope, evidence, amount, or state precondition is required? |
| Data Change | Which columns/tables are written? |
| Next State | What exact state results? |
| Ownership | Is current assignee preserved, cleared, or changed? |
| Receipt Effect | Are attachments/reviews created, preserved, removed, or reset? |
| Customer/Invoice Effect | Is canonical customer/invoice financial data changed? |
| Audit Event | Which `case_actions`/audit event is recorded? |
| Side Effects | Tags, profile completion, automatic reconciliation, notifications, etc. |
| Regression Tests | Which existing paths must remain unchanged? |

### Example

```text
Actor: assigned deficiency agent
Current State: RETURNED_TO_DEFICIENCY
Department: deficiency
Action: follow-up / extra_payment_claim
Guard: deficiency case access + follow-up permission/fallback
Data Change: deficiency_result, optional customer_claimed_amount, description/update timestamp
Next State: DEFICIENCY_UNDER_REVIEW
Ownership: preserve deficiency_agent_id
Audit Event: FINANCE_RETURN_FOLLOWUP_RECORDED
Side Effects: no new finance handoff until explicitly selected
```

---

## 24. Documentation boundary

This document describes **what the v0.8.65.17 source currently does**. It does not prove:

- what version is active on production/staging;
- effective WordPress role/capability records on a particular installation;
- historical database consistency;
- that every UI route is reachable for every role in runtime;
- that legacy data follows the newest state assumptions.

Those require runtime/database evidence separately.
