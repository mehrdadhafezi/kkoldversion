# Biawin CRM — Ownership Reconciliation Architecture Contract

> Baseline: **v0.8.65.17**  
> Status: **APPROVED FOR IMPLEMENTATION — NOT IMPLEMENTED**  
> Scope: ownership locking, ownership disputes, invoice/phone reassignment review, evidence, OTP, audit, profile hold, and post-transfer reconciliation.  
> This document defines the intended architecture for a future implementation. It does **not** claim that these tables, capabilities, routes, states, or controls already exist in runtime.

---

## 1. Purpose

The current CRM allows customer profiles, phone numbers, invoices, invoice-phone relationships, services, deficiency cases, and finance review to interact. Once a customer profile is considered complete, invoices belonging to that profile must no longer be silently or directly reassigned to another profile.

The new domain introduced by this contract is called:

**Ownership Reconciliation**  
Persian UI name: **تطبیق مالکیت**

Recommended operational roles:

- **کارشناس تطبیق مالکیت** (`Ownership Specialist`)
- **سرپرست تطبیق مالکیت** (`Ownership Supervisor`) — optional for MVP if supervision/assignment is required from day one.

The purpose is to separate:

1. the current canonical owner of an invoice;
2. the current canonical owner of a phone number;
3. the phone associated with an invoice;
4. a claim that one or more of those relationships are wrong;
5. the evidence and review process used to decide the claim;
6. the controlled transfer that follows an approved decision.

A claim is **not** itself a transfer.

---

## 2. Business decisions already confirmed

The following rules are treated as confirmed inputs to this contract.

### BR-01 — Completed-profile invoice lock

When an invoice belongs to a profile that has reached completed status, that invoice must be protected from direct reassignment to another customer profile.

A user who believes the locked invoice belongs to another profile must create an ownership review request instead of directly attaching/moving the invoice.

### BR-02 — Claim must support draft before submission

An ownership request may be saved as **Draft**. A Draft:

- does not transfer any invoice;
- does not transfer any phone;
- does not place operational holds on either profile;
- may contain selected assets, explanation, and evidence.

Only **Submit** turns the draft into a formal ownership case.

### BR-03 — Both profiles enter ownership review after Submit

After formal submission, the current-owner profile and claimant profile participate in one ownership case and become protected from conflicting ownership-changing operations until the case is decided.

This protection must be implemented as an ownership-related **hold**, not by overwriting the profile's existing journey/completeness/deficiency state.

### BR-04 — A transfer may make the old profile incomplete

If an invoice is transferred away from a previously completed profile and the source profile no longer reconciles financially after recalculation, the source profile must return to the normal deficiency process.

Previously completed status does not freeze the old financial totals forever.

### BR-05 — One phone may relate to multiple invoices

A single phone number may be associated with several invoice codes.

Therefore, claiming one invoice does not imply claiming every other invoice related to that phone.

### BR-06 — Every invoice has exactly one phone

Business rule: **each invoice code must have exactly one phone number associated with it**.

This is a future invariant that implementation must enforce consistently across manual entry, imports, edits, ownership transfer, and API/AJAX routes.

The current schema/runtime may not yet enforce this invariant; implementation must include a migration/compatibility plan rather than assuming it already holds historically.

### BR-07 — Invoice transfer and phone-ownership transfer are independent decisions

When a user claims an invoice, the requester must explicitly state what should happen to the invoice's phone relationship. The system must not infer phone transfer from invoice transfer.

For each claimed invoice, the requester selects one of the following proposed phone-resolution plans:

1. **Keep the invoice's current phone** (`KEEP_CURRENT_PHONE`)
2. **Use a phone already belonging to the destination profile** (`USE_TARGET_PROFILE_PHONE`)
3. **Claim the current invoice phone for ownership transfer as well** (`CLAIM_CURRENT_PHONE`)
4. **Propose a new phone not yet attached to the destination profile** (`PROPOSE_NEW_PHONE`)

No approved transfer may leave an invoice without exactly one phone.

### BR-08 — Requester proposes; specialist decides

The requester records the proposed transfer plan. The Ownership Specialist makes the final decision.

The specialist may change the requester's proposed plan, but any such change requires:

- an explicit reason;
- actor identity;
- timestamp;
- before/after plan values;
- an immutable audit/timeline event.

Recommended event: `REQUESTED_TRANSFER_PLAN_CHANGED`.

### BR-09 — Phone claim does not automatically absorb all invoices

If a phone is associated with three invoices and the claimant requests only one invoice plus the phone, only the explicitly selected invoice is in the requested invoice scope.

The other invoices remain associated with the source profile/history until separately decided.

The system must never perform the rule:

> phone moved ⇒ all invoices related to that phone moved

unless every invoice is individually and explicitly included in an approved transfer plan.

### BR-10 — History must not be destroyed

When ownership or invoice-phone bindings change, prior relationships must remain auditable. Historical association must not be erased simply because canonical ownership changed.

### BR-11 — Ownership transfer must not auto-collapse either profile

Profiles participating in an ownership case must not be automatically deleted/collapsed as a side effect of the transfer, even if a source profile becomes empty of invoices.

Their audit/history is evidence relevant to the ownership decision.

---

## 3. Domain boundaries

Ownership Reconciliation is a new domain and must **not** be embedded as additional branching inside the large Deficiency Workflow class.

Recommended conceptual boundaries:

```text
Customer Domain
  ├── Customer/Profile
  ├── Phones
  ├── Invoices
  └── Services

Deficiency Domain
  └── Financial mismatch workflow

Finance Domain
  └── Receipt review / financial verification

Ownership Reconciliation Domain
  ├── Ownership Locks
  ├── Ownership Cases
  ├── Claimed Assets
  ├── Transfer Plans
  ├── Evidence
  ├── OTP Challenges
  ├── Operational Holds
  ├── Decisions
  └── Immutable Ownership Events / Transfer Ledger
```

Deficiency may be triggered **after** an approved transfer and recalculation, but Deficiency is not the system that decides ownership disputes.

---

## 4. Canonical concepts

### 4.1 Invoice ownership

Canonical current invoice owner is the profile referenced by the invoice ownership field used by the existing CRM (currently inferred from invoice/customer linkage in the baseline source).

Ownership transfer changes only after an approved Ownership Case decision.

### 4.2 Phone ownership

Canonical current phone owner is the profile referenced by the phone ownership linkage used by the CRM.

Phone ownership transfer is independent from invoice ownership transfer.

### 4.3 Invoice phone

Each invoice must have exactly one current phone association under the target business rule.

This phone may be:

- the current phone already attached to the invoice;
- a phone owned by the target profile;
- a phone whose ownership is itself being transferred;
- a newly proposed phone that becomes valid only after review/verification.

### 4.4 Historical relationships

Ownership history and prior invoice-phone relationships must remain queryable for audit. A change in canonical relationship is not permission to erase previous evidence.

---

## 5. Lock model

### 5.1 Why a persistent lock is required

The lock must not be derived only from the profile's current `complete/incomplete` value. Completion can change after recalculation. The fact that an invoice became protected when ownership was considered finalized is a separate operational fact.

Therefore, lock state should be persistent and auditable.

### 5.2 Proposed table: `bcrm_ownership_locks`

Recommended fields:

```text
id
asset_type              invoice | phone
asset_id
customer_id              canonical owner at lock time
lock_reason              PROFILE_COMPLETED | OWNERSHIP_TRANSFER_APPROVED | MANUAL_POLICY
locked_at
locked_by_user_id         nullable for system lock
locked_by_event
released_at               nullable
released_by_user_id       nullable
released_by_case_id       nullable
release_reason            nullable
created_at
updated_at
```

### 5.3 Hard guard requirement

A lock must be enforced in backend service methods, not only by hiding UI buttons.

Every entry point capable of changing invoice or phone ownership must consult the ownership guard before mutation, including as applicable:

- manual invoice attach/reassign;
- invoice + phone attach flows;
- merge/reconciliation paths that rewrite owner IDs;
- imports;
- AJAX;
- REST;
- admin-post handlers;
- internal service methods.

Recommended API boundary:

```text
OwnershipLockService::assert_can_change_invoice_owner(...)
OwnershipLockService::assert_can_change_phone_owner(...)
```

No controller should bypass this domain service.

---

## 6. Ownership Case state machine

Approved case states and approval routing:

```text
DRAFT
  ↓ submit
SUBMITTED
  ↓ apply global read-only hold to both profiles
  ↓ risk evaluation
ASSIGNED
  ↓ specialist opens/starts
UNDER_REVIEW
  ├── AWAITING_EVIDENCE
  ├── AWAITING_CUSTOMER_CONFIRMATION
  └── UNDER_REVIEW
          ↓ specialist decision
          ├── withdrawal/rejection ───────────────► DECIDED ─► CLOSED
          └── transfer proposed
                    ↓ re-evaluate risk
                    ├── NORMAL ───────────────────► DECIDED ─► execute ─► CLOSED
                    └── HIGH_RISK
                              ↓
                    AWAITING_SUPERVISOR_APPROVAL
                              ↓ supervisor approval
                            DECIDED
                              ↓
                           execute
                              ↓
                            CLOSED
```

A supervisor may escalate a `NORMAL` case to `HIGH_RISK`. No user, including supervisor/admin through normal workflow UI, may manually downgrade a system-classified `HIGH_RISK` case to `NORMAL`; changing that outcome requires changing the risk rule/configuration with audit.

Alternative terminal path:

```text
DRAFT → CANCELLED
SUBMITTED/ASSIGNED/UNDER_REVIEW → CANCELLED
```

Cancellation after formal submission must be permission-gated and audited. A submitted case should not simply be deleted.

### 6.1 State semantics

| State | Meaning | Profile Hold |
|---|---|---|
| `DRAFT` | Requester is preparing claim | No |
| `SUBMITTED` | Formal ownership dispute exists | Yes |
| `ASSIGNED` | Assigned to Ownership Specialist | Yes |
| `UNDER_REVIEW` | Specialist actively reviewing | Yes |
| `AWAITING_EVIDENCE` | Waiting for documents/evidence | Yes |
| `AWAITING_CUSTOMER_CONFIRMATION` | Waiting for customer/OTP/consent | Yes |
| `AWAITING_SUPERVISOR_APPROVAL` | High-risk transfer reviewed by specialist and waiting for supervisor approval | Yes |
| `DECIDED` | Decision recorded, transfer/finalization pending or executing | Yes until safe finalization |
| `CLOSED` | Decision applied and post-processing complete | No |
| `CANCELLED` | Formal case cancelled under policy | No after safe release |

---

## 7. Case parties

Each case has exactly two primary profiles for MVP:

1. **Current Owner Profile** — the profile currently holding the disputed invoice/phone.
2. **Claimant Profile** — the profile for which the requester claims ownership.

Recommended fields in `bcrm_ownership_cases`:

```text
id
case_code
current_owner_customer_id
claimant_customer_id
status
claim_reason
request_note
requested_by_user_id
assigned_to_user_id
assigned_by_user_id
risk_level                 NORMAL | HIGH_RISK
risk_reasons_json
risk_evaluated_at
submitted_at
assigned_at
review_started_at
decision
decision_method
decision_note
decided_by_user_id
decided_at
supervisor_approval_status  NOT_REQUIRED | PENDING | APPROVED | REJECTED
supervisor_user_id
supervisor_note
supervisor_decided_at
closed_at
created_at
updated_at
row_version / lock_version   (recommended for concurrency)
```

Suggested case code format:

```text
OWN-YYYY-NNNNNN
```

---

## 8. Claimed assets and transfer plan

The claim must be asset-based, not one global checkbox.

### 8.1 Proposed table: `bcrm_ownership_case_assets`

Recommended fields:

```text
id
case_id
asset_type                 invoice | phone
asset_id
source_customer_id
target_customer_id
requested_action           TRANSFER | KEEP | VERIFY_ONLY
requested_phone_resolution KEEP_CURRENT_PHONE
                           USE_TARGET_PROFILE_PHONE
                           CLAIM_CURRENT_PHONE
                           PROPOSE_NEW_PHONE
requested_phone_id         nullable
proposed_phone_normalized  nullable
request_note               nullable

final_action               nullable until decision
final_phone_resolution     nullable
final_phone_id             nullable
final_phone_normalized     nullable
specialist_override        boolean
specialist_override_reason nullable

snapshot_json
created_at
updated_at
```

### 8.2 Invoice-by-invoice explicitness

If a phone is associated with:

```text
Phone P
 ├── Invoice A
 ├── Invoice B
 └── Invoice C
```

and only Invoice B is claimed, the case must record only Invoice B unless A/C are separately selected.

### 8.3 No invoice without phone

For every approved invoice transfer, the final plan must resolve exactly one phone before the transfer can commit.

Validation rule:

```text
approved_invoice_transfer => exactly_one(final_invoice_phone)
```

---

## 9. Requester flow

### 9.1 Entry point

When a user searches/enters an invoice code and the invoice is locked to another completed profile, direct attach must be blocked.

UI should show:

```text
این فاکتور به یک پروفایل تکمیل‌شده متصل و قفل شده است.
[مشاهده مالک فعلی]
[ثبت درخواست بررسی مالکیت]
```

### 9.2 Draft form

The requester must be able to:

- see claimant profile;
- see current owner profile summary sufficient to identify conflict;
- select one or more specific invoice codes;
- optionally select/claim a phone number;
- choose a phone-resolution plan for every claimed invoice;
- write an explanation;
- upload evidence such as receipt/payment proof or other documentation;
- save Draft;
- submit for Ownership Review.

### 9.3 Submit validation

Before Submit:

- claimant and current owner must be distinct;
- selected invoice(s) must still exist;
- current canonical owners must be re-read from DB;
- lock state must still be valid;
- each invoice must have a requested phone-resolution plan;
- selected destination-profile phone must actually belong to destination profile at submit time;
- newly proposed phone must pass normalization/format validation;
- conflicting active case rules must pass;
- snapshot must be captured.

---

## 10. Evidence model

Reuse the existing attachment infrastructure where possible rather than inventing a second file store.

Recommended:

```text
bcrm_attachments.object_type = 'ownership_case'
bcrm_attachments.object_id   = ownership_case_id
```

Recommended `file_kind` values:

```text
ownership_claim_receipt
ownership_identity_document
ownership_other_evidence
ownership_consent_evidence
ownership_otp_supporting_file
```

Evidence metadata should record who uploaded it and at what stage.

Deleting/replacing evidence after a formal decision should be restricted; preferred model is append-only replacement with audit rather than silent destructive deletion.

---

## 11. Profile hold model

### 11.1 Hold is orthogonal to existing profile states

Do not change the profile's current journey, deficiency status, or financial completeness merely because an ownership dispute is open.

Use a separate hold table.

### 11.2 Proposed table: `bcrm_customer_holds`

```text
id
customer_id
hold_type                  OWNERSHIP_REVIEW
case_id
scope                      IDENTITY_FINANCIAL (recommended default)
active
created_at
created_by_user_id
released_at
released_by_user_id
release_reason
```

### 11.3 Approved hold scope — global read-only for ordinary staff

**APPROVED:** from formal `SUBMITTED` until safe `CLOSED`/`CANCELLED`, both participating profiles are globally read-only to ordinary operational staff.

While the hold is active, ordinary workspaces may display the profile and an ownership-review notice, but they must block all mutating actions, including at minimum:

- invoice ownership or invoice data mutation;
- phone ownership or phone data mutation;
- profile merge/collapse;
- deficiency/finance/customer-profile actions that write canonical profile data;
- manual completion/finalization;
- imports or bulk tools that would mutate either profile;
- any internal helper reachable from ordinary operational flows.

Existing Deficiency/Finance/other workflow records are **not cancelled or deleted**. They are paused by the customer hold and resume from their preserved state after the Ownership Case closes. Post-transfer recalculation may then legitimately reroute a profile, including creating/reopening Deficiency when the approved transfer creates a mismatch.

Mutation while held is allowed only through the Ownership Reconciliation domain under its explicit permissions, plus separately governed emergency-admin tooling if such tooling is ever implemented.

---

## 12. Workspace and permissions

Recommended workspace: **تطبیق مالکیت**

Recommended roles/capabilities:

```text
submit_ownership_claim
view_own_ownership_claims
view_ownership_cases
assign_ownership_cases
review_ownership_cases
request_ownership_evidence
record_ownership_contact
issue_ownership_otp
verify_ownership_otp
approve_ownership_transfer
reject_ownership_claim
withdraw_ownership_claim
manage_ownership_evidence
view_ownership_audit
manage_ownership_policy
```

### 12.1 Permission matrix

| Action | Requesting Expert | Ownership Specialist | Ownership Supervisor | CRM Admin |
|---|---:|---:|---:|---:|
| Create draft claim | Yes | Yes if operationally needed | Yes | Yes |
| Edit own draft | Yes | No unless takeover policy | Yes | Yes |
| Submit claim | Yes | Yes if creator | Yes | Yes |
| View submitted case | Limited to involved case/profile scope | Assigned/authorized | Yes | Yes |
| Assign/reassign case | No | No by default | Yes | Yes |
| Start review | No | Yes when assigned | Yes | Yes |
| Add evidence | Yes while allowed | Yes | Yes | Yes |
| Request more evidence | No | Yes | Yes | Yes |
| Record customer contact | No | Yes | Yes | Yes |
| Send/verify OTP | No by default | Yes | Yes | Yes |
| Change proposed transfer plan | No after submit | Yes, reason required | Yes, reason required | Yes, reason required |
| Withdraw claimant request | Requestable/recordable under policy | Record final withdrawal | Yes | Yes |
| Reject claim | No | Yes | Yes | Yes |
| Approve transfer | No | Yes for `NORMAL` cases only | Yes; required for `HIGH_RISK` | Yes subject to same risk/approval workflow |
| Execute ownership mutation directly outside case | No | No | No | Emergency admin only if separately governed/audited |
| View audit | Relevant case only if allowed | Yes | Yes | Yes |

**APPROVED SoD policy:** the assigned Ownership Specialist may investigate and approve a `NORMAL` case. A `HIGH_RISK` transfer requires a second approval by an Ownership Supervisor before canonical ownership mutation. Risk classification is system-driven; specialists/supervisors may escalate but may not manually downgrade `HIGH_RISK`.

---

## 12.2 Approved risk engine

Risk classification is computed by the system at submission and re-evaluated whenever case scope, transfer plan, evidence conflict, or OTP state materially changes.

Initial rules:

| Condition | Risk |
|---|---|
| One invoice only; no phone-ownership transfer; specialist keeps requester plan | `NORMAL` |
| Transfer of a disputed phone number | `HIGH_RISK` |
| Invoice + phone ownership transfer | `HIGH_RISK` |
| More than one invoice in the case | `HIGH_RISK` |
| Specialist adds any new asset after submit (formal amendment) | `HIGH_RISK` |
| Specialist changes requester transfer plan materially | `HIGH_RISK` |
| Conflicting evidence between profiles | `HIGH_RISK` |
| OTP failed/expired/contradictory while phone is under claim | `HIGH_RISK` |
| Transfer causes the old completed profile to become incomplete | Not high-risk by itself; handled as normal post-transfer recalculation/Deficiency side effect |
| Claimant withdrawal or claim rejection with no transfer | `NORMAL` |

A specialist or supervisor may manually **escalate** a `NORMAL` case with a mandatory reason. Manual downgrade of `HIGH_RISK` is forbidden. Risk reasons are persisted and audited, not recomputed only for display.

---

## 13. Specialist review UI

Recommended split-view page:

```text
┌────────────────────────────┬────────────────────────────┐
│ مالک فعلی                  │ مدعی                       │
│ Current Owner Profile      │ Claimant Profile           │
│                            │                            │
│ نام/شناسه                  │ نام/شناسه                  │
│ شماره‌ها                   │ شماره‌ها                   │
│ فاکتورها                   │ فاکتورها                   │
│ مبالغ                      │ مبالغ                      │
│ وضعیت تکمیل                │ وضعیت تکمیل                │
│ خدمات مرتبط                │ خدمات مرتبط                │
│ Timeline خلاصه             │ Timeline خلاصه             │
└────────────────────────────┴────────────────────────────┘

Claimed Assets
Evidence
Contacts / OTP
Requested Transfer Plan
Final Transfer Plan
Decision
```

The specialist must see both the **request-time snapshot** and relevant **current live values**, with differences highlighted.

---

## 14. Review outcomes

Required final outcomes:

### 14.1 `CLAIM_WITHDRAWN` — انصراف مدعی

Required:

- explanation;
- actor;
- timestamp.

Effects:

- no ownership transfer;
- release holds safely;
- close case;
- keep all evidence/history.

### 14.2 `CLAIM_REJECTED` — رد ادعا

Required because a claimant may not voluntarily withdraw but the evidence may still be insufficient/incorrect.

Required:

- rejection reason;
- explanation;
- reviewer identity;
- evidence references as applicable.

Effects:

- no ownership transfer;
- release holds safely;
- close case.

### 14.3 `TRANSFER_APPROVED` — تأیید جابه‌جایی

Approval must include a final explicit transfer plan for every asset.

Recommended verification methods:

```text
CLAIMANT_RECEIPT
CURRENT_OWNER_CONSENT
OTP_VERIFICATION
DOCUMENT_REVIEW
MULTI_EVIDENCE
```

Verification method supports the decision; it does not replace the decision record.

---

## 15. Approval evidence modes

### 15.1 Claimant receipt/payment evidence

If approval relies on a receipt uploaded by the claimant/requester:

Required:

- explanation;
- at least one relevant attachment;
- specialist decision note.

### 15.2 Current-owner consent

If the current owner agrees the invoice is not theirs:

Record at minimum:

- contact timestamp;
- contact outcome;
- staff member;
- explanation/statement summary;
- any evidence/recording reference permitted by organizational policy.

A plain checkbox with no audit context is insufficient.

### 15.3 OTP verification

OTP is new functionality and must be scoped to an ownership case and purpose.

**APPROVED OTP target policy:** OTP is used when the **phone number itself is a claimed/disputed asset**. The challenge is sent to that exact disputed phone number, regardless of whether the current canonical profile is the old owner or the claimant is asserting that the number belongs to the new profile.

OTP proves access/control of that phone at a point in time; it does **not by itself prove invoice ownership or legal ownership of the number**. It is supplementary evidence supporting the specialist/supervisor decision.

---

## 16. OTP architecture

Recommended table: `bcrm_ownership_otp_challenges`

```text
id
case_id
customer_id
phone_id                  nullable for proposed phone
phone_normalized
purpose                   CONFIRM_PHONE_CONTROL | CONFIRM_TRANSFER_CONSENT
asset_type                nullable
asset_id                  nullable
otp_hash
expires_at
attempt_count
max_attempts
sent_at
verified_at
consumed_at
created_by_user_id
created_at
```

Rules:

- issue OTP only against the exact phone asset under claim/dispute for this approved policy;
- bind the challenge to `case_id + phone + purpose` and, where applicable, the phone asset row;
- never treat OTP success alone as sufficient proof of invoice ownership;
- never store plaintext OTP after issuance;
- short expiry;
- attempt limit;
- resend throttling;
- bind challenge to case + purpose + phone;
- successful verification cannot be reused for another case/asset/purpose;
- record provider/message delivery metadata where available;
- audit send, failure, expiry, verification, and consumption.

OTP verification must not itself mutate invoice/phone ownership.

---

## 17. Specialist override rule

The requester proposes the transfer plan. The specialist can change it.

If the specialist changes any of these:

- selected invoice set;
- requested phone-resolution mode;
- destination phone;
- whether current phone ownership is transferred;
- proposed new phone;

then the system must require an override reason and create an immutable event containing:

```text
case_id
asset_id
requested_plan_before
final_plan_after
override_reason
actor_user_id
timestamp
```

Recommended event: `REQUESTED_TRANSFER_PLAN_CHANGED`.

**APPROVED scope-expansion policy:** the Ownership Specialist may add a new invoice or phone to the same case, but only through a formal **case amendment**. The amendment requires a reason, before/after snapshot, actor/timestamp, and immutable audit event. Any post-submit scope expansion automatically reclassifies the case as `HIGH_RISK` and therefore requires supervisor approval before transfer execution. Silent expansion is forbidden.

---

## 18. Concurrency and conflict rules

### 18.1 One active ownership case per invoice

For MVP, one invoice must not participate in two simultaneously active ownership cases.

On new request, if an active case already contains that invoice:

```text
This invoice already has an active ownership review case.
```

The second request is blocked or routed to the existing case according to future business policy.

### 18.2 Phone conflicts

A phone whose ownership is explicitly under review should also be protected from a second ownership-transfer case until the first case closes.

### 18.3 Revalidation before decision execution

Immediately before applying an approved transfer, the service must re-read:

- invoice current owner;
- phone current owner;
- invoice-phone binding;
- active locks;
- active holds;
- source/target profile existence;
- case version/state.

If request-time assumptions no longer match live state, the transfer must fail closed and return to review rather than overwrite newer changes.

---

## 19. Snapshot requirement

At Submit, capture a case snapshot sufficient to prove what the requester and system saw at request time.

Snapshot should include at minimum:

```text
Current Owner Profile
Claimant Profile
Selected invoices
Invoice amounts
Current invoice owner IDs
Current invoice phone for each invoice
Relevant phone owner IDs
Profile completion states
Declared/registered totals relevant to reconciliation
Invoice-linked services
Active locks
```

The snapshot is evidence and must not be silently rewritten when live data changes.

---

## 20. Transfer execution contract

An approved transfer must be executed by a dedicated ownership transfer service, not by directly reusing a UI controller and not by allowing arbitrary calls to current attach helpers without ownership-specific guards.

Recommended command shape:

```text
OwnershipTransferService::executeApprovedCase(case_id)
```

### 20.1 Transaction boundary

Inside one DB transaction, for the approved plan:

1. lock/re-read Ownership Case row;
2. confirm state is eligible for execution;
3. revalidate canonical owners and conflict locks;
4. revalidate final phone resolution for every invoice;
5. update invoice owner(s);
6. update phone owner(s) only when explicitly approved;
7. update current invoice-phone relationship so every invoice has exactly one phone;
8. transfer invoice-derived services consistently with invoice ownership;
9. write ownership transfer ledger/events;
10. close/release superseded locks and create new destination locks as required;
11. mark decision execution result;
12. commit.

### 20.2 After commit

After the ownership transaction commits successfully:

1. recalculate Source Profile;
2. recalculate Target Profile;
3. if Source Profile is now financially incomplete, route it into normal Deficiency handling;
4. if Target Profile becomes complete/incomplete, preserve normal CRM reconciliation rules;
5. release Ownership Holds only after post-transfer state is safe;
6. close Ownership Case;
7. record all resulting workflow events.

### 20.3 Failure handling

If post-commit recalculation fails, the ownership transfer must not be silently rolled back via ad-hoc reverse updates. Record a recoverable system error state/event and keep appropriate hold until repair/reconciliation completes.

Implementation should decide whether `DECIDED` needs a substate such as `EXECUTION_FAILED` / `FINALIZATION_PENDING`.

---

## 21. Services linked to transferred invoices

Current architecture indicates invoice ownership and invoice-derived services can be coupled.

Rule for the new domain:

> If an invoice is transferred to another profile, all services whose ownership/customer identity is derived from that invoice must remain consistent with the invoice owner.

Do not allow:

```text
Invoice.customer = Profile B
Service.invoice   = same invoice
Service.customer  = Profile A
```

unless a separately documented service-domain rule explicitly permits it.

Implementation must inventory current service linkages before coding transfer mutation.

---

## 22. Payments

Where payments are linked to invoice IDs rather than directly duplicated as profile ownership, they should remain attached to the invoice when the invoice moves.

Implementation must verify actual baseline schema/routes before assuming no additional payment ownership fields need changes.

---

## 23. Post-transfer deficiency effects

Confirmed business rule:

If transferring an invoice away from a completed Source Profile causes a financial mismatch, Source Profile must re-enter Deficiency.

Conceptual flow:

```text
Ownership Transfer Approved
       ↓
Invoice moves A → B
       ↓
Recalculate A and B
       ↓
A no longer reconciles
       ↓
Normal Deficiency Case / routing for A
```

Ownership Review must not itself become the deficiency workflow. It only triggers normal recalculation and lets existing Deficiency rules handle resulting mismatch.

Duplicate-case guards must be respected if a valid active Deficiency Case already exists.

---

## 24. Invoice-phone history model

Because every invoice must have exactly one current phone but historical relationships must remain auditable, the implementation must distinguish **current binding** from **history**.

Two safe design options exist:

### Option A — Extend current invoice-phone model

Add fields such as:

```text
is_current
valid_from
valid_to
source
verified_by_case_id
created_by_user_id
```

with a constraint ensuring exactly one current phone per invoice.

### Option B — Dedicated current binding + append-only history

For example:

```text
bcrm_invoice_phone_bindings       (one current binding per invoice)
bcrm_invoice_phone_history        (append-only changes)
```

**Recommended:** Option B if the existing `invoice_phones` table is widely used and changing its semantics could cause regressions. During implementation, inventory all readers/writers first.

No implementation should simply delete the previous invoice-phone row without preserving history.

---

## 25. Audit and ownership ledger

Recommended append-only table: `bcrm_ownership_case_events`

```text
id
case_id
event_type
actor_user_id
asset_type
asset_id
payload_json
created_at
```

Minimum event vocabulary:

```text
CASE_DRAFT_CREATED
CASE_DRAFT_UPDATED
EVIDENCE_ADDED
CASE_SUBMITTED
PROFILE_HOLD_APPLIED
CASE_ASSIGNED
REVIEW_STARTED
MORE_EVIDENCE_REQUESTED
CONTACT_RECORDED
OTP_SENT
OTP_FAILED
OTP_VERIFIED
REQUESTED_TRANSFER_PLAN_CHANGED
CLAIM_WITHDRAWN
CLAIM_REJECTED
TRANSFER_APPROVED
INVOICE_OWNER_CHANGED
PHONE_OWNER_CHANGED
INVOICE_PHONE_CHANGED
SERVICE_OWNER_ALIGNED
OWNERSHIP_LOCK_RELEASED
OWNERSHIP_LOCK_CREATED
PROFILE_RECALCULATED
DEFICIENCY_TRIGGERED
PROFILE_HOLD_RELEASED
CASE_CLOSED
CASE_EXECUTION_FAILED
```

Audit payload must contain enough before/after information to reconstruct ownership changes.

---

## 26. Delete policy

Submitted ownership cases, decisions, and transfer events should not be physically deleted through normal UI.

Preferred behavior:

- Draft may be deletable by creator if no formal submission occurred, subject to audit policy.
- Submitted cases may be cancelled, not deleted.
- Evidence after decision should be immutable or replaced through append-only supersession.
- Ownership events/ledger are append-only.

---

## 27. Proposed module/class boundaries

Do not add this feature wholesale to `class-biawin-crm-deficiency-workflow.php`.

Suggested files/classes:

```text
includes/class-biawin-crm-ownership-schema.php
includes/class-biawin-crm-ownership-service.php
includes/class-biawin-crm-ownership-lock-service.php
includes/class-biawin-crm-ownership-workflow.php
includes/class-biawin-crm-ownership-authorization.php
includes/class-biawin-crm-ownership-transfer-service.php
includes/class-biawin-crm-ownership-otp.php
includes/class-biawin-crm-ownership-repository.php   (optional if repository pattern is adopted)
admin/class-biawin-crm-ownership-admin.php           (or equivalent admin/workspace controller)
```

Frontend/UI code should call service boundaries; it should not implement canonical ownership changes directly.

---

## 28. Integration with current architecture

### 28.1 Customer/Profile

Ownership domain reads both profiles and writes owner relationships only through approved transfer service.

### 28.2 Deficiency

Ownership domain does not change Deficiency state directly during investigation. After approved transfer and recalculation, normal Deficiency service is invoked if required.

### 28.3 Finance

Finance receipt review does not determine invoice ownership by itself. Finance evidence may be shown to Ownership Specialist if authorized and relevant.

### 28.4 Attachments

Existing attachment infrastructure should be reused for evidence with ownership-specific object/file kinds.

### 28.5 Timeline

Ownership Case gets its own detailed event stream. Customer profile timeline may also receive summarized cross-domain events such as `OWNERSHIP_CASE_OPENED`, `OWNERSHIP_TRANSFER_APPROVED`, `OWNERSHIP_CASE_CLOSED`.

---

## 29. Required backend invariants

The implementation must fail closed if any of these invariants are violated:

1. A locked completed-profile invoice cannot be directly reassigned outside approved Ownership Case execution.
2. Claim submission never changes canonical ownership.
3. Draft never applies profile hold.
4. Submitted/active case applies ownership-related hold to both profiles.
5. Every approved invoice transfer resolves exactly one current phone.
6. Phone transfer is never inferred from invoice transfer.
7. Moving one invoice never auto-moves sibling invoices sharing a phone.
8. Specialist override requires reason and audit.
9. Transfer is executed only from an approved case in an eligible state.
10. Source and target profiles are recalculated after successful ownership transfer.
11. Source profile re-enters Deficiency if recalculation makes it incomplete.
12. Profiles in Ownership Cases are not auto-collapsed.
13. Historical owner/binding evidence is preserved.
14. The same invoice cannot be in multiple active ownership transfer cases for MVP.
15. All owner changes are transactional and auditable.

---

## 30. UI invariants

UI must never imply that a submitted claim already changed ownership.

Distinct labels should be used:

- **درخواست انتقال** / **ادعای مالکیت** before decision;
- **مالک فعلی** for current canonical owner;
- **مدعی** for claimant;
- **طرح پیشنهادی انتقال** for requester selection;
- **تصمیم نهایی کارشناس تطبیق** for approved/rejected outcome.

When a locked invoice is searched from another profile, show the lock and route user to claim creation rather than silently attaching it.

---

## 31. Migration and compatibility requirements

Before implementation, perform a data audit to determine:

- invoices with zero phone relationships;
- invoices with more than one phone relationship;
- duplicate/ambiguous phone normalization;
- invoices attached to completed profiles;
- current profile/invoice ownership anomalies;
- services whose customer owner differs from invoice owner;
- any legacy merge/attach routes capable of bypassing new ownership guards.

Because the new confirmed rule is **exactly one phone per invoice**, historical exceptions must be resolved before strict enforcement. **APPROVED legacy-data policy:** classify historical data into three groups: (1) clean → migrate directly; (2) deterministically repairable → migrate/repair automatically with audit; (3) ambiguous → place into a dedicated **Historical Ownership Reconciliation** queue and do not auto-change ownership. Do not add a hard exactly-one-phone constraint until the audit/migration proves legacy compatibility or the ambiguous records are explicitly grandfathered.

---

## 32. Implementation sequencing recommendation

Recommended safe sequence:

```text
Phase 0 — Data/route audit only
Phase 1 — Schema + event/lock/hold foundations
Phase 2 — Backend ownership guards around existing attach/merge paths
Phase 3 — Draft/Submit Ownership Case flow + evidence
Phase 4 — Ownership workspace + assignment + side-by-side review
Phase 5 — Decision + transfer plan + transactional execution
Phase 6 — OTP evidence
Phase 7 — Migration/enforcement of exactly-one-phone-per-invoice
Phase 8 — Runtime acceptance + regression suite
```

Do not begin with the OTP or UI. The first safety feature must be the backend lock/guard model.

---

## 33. Required acceptance tests

At minimum, implementation must prove:

1. Locked invoice cannot be directly attached to a second profile through UI, AJAX, REST, import, or internal helper.
2. Draft claim changes no canonical data and applies no hold.
3. Submit creates one formal case, snapshots both profiles, and applies holds.
4. Requester can select one invoice out of several sharing one phone.
5. Requester can choose each supported phone-resolution plan per invoice.
6. Evidence upload persists against Ownership Case.
7. Specialist sees both profiles side by side with request-time snapshot and current values.
8. Specialist can reject claim without ownership changes.
9. Specialist can record claimant withdrawal.
10. Specialist override cannot save without reason.
11. OTP verification cannot be reused across case/purpose.
12. Approved invoice-only move does not auto-move sibling invoices.
13. Approved phone move does not auto-move unrelated invoices.
14. Approved invoice always ends with exactly one current phone.
15. Invoice-linked services remain consistent with invoice owner.
16. Ownership history survives transfer.
17. Source profile recalculates after transfer.
18. Source profile enters Deficiency when transfer creates mismatch.
19. Destination profile recalculates after transfer.
20. Both ownership holds release only after successful finalization.
21. Failed transfer does not leave partially changed owners.
22. Concurrent second case for same invoice is blocked.
23. Participating profiles are never auto-collapsed by transfer.
24. All decision and transfer events are present in audit ledger.

---

## 34. Governance decisions — APPROVED

All five governance decisions required for coding approval are now **APPROVED**.

### GOV-01 — Segregation of Duties and risk approval — APPROVED

- `NORMAL`: assigned Ownership Specialist may investigate, decide, and approve the transfer.
- `HIGH_RISK`: specialist investigates and proposes the final decision; Ownership Supervisor must provide the second approval before canonical transfer executes.
- Risk level is system-classified from explicit rules in §12.2.
- Specialist/supervisor may escalate `NORMAL` to `HIGH_RISK` with mandatory reason.
- Manual downgrade of system-classified `HIGH_RISK` is not permitted.

### GOV-02 — Profile hold scope — APPROVED

From `SUBMITTED` until safe close/cancellation, **both profiles are globally read-only to ordinary staff**. Existing workflow records are preserved/paused, not deleted or cancelled. Ownership-domain authorized users may perform only the mutations needed by the Ownership Case.

### GOV-03 — Specialist scope expansion — APPROVED

The Ownership Specialist may add another invoice/phone to the same case after submission, but only through a formal audited **amendment** with mandatory reason and before/after snapshot. Scope expansion automatically makes the case `HIGH_RISK`.

### GOV-04 — OTP target and evidentiary weight — APPROVED

When a phone number itself is disputed, OTP is sent to that exact disputed number, whether the current canonical profile is the prior owner or the new claimant is asserting ownership. OTP proves control/access at a point in time and is **supplementary evidence**, not standalone proof of invoice ownership. OTP is case/phone/purpose scoped, expiring, attempt-limited, and non-reusable.

### GOV-05 — Historical inconsistent data — APPROVED

Before enforcing exactly one phone per invoice:

1. clean records → direct migration;
2. deterministically repairable records → automatic repair/migration with audit;
3. ambiguous records → `Historical Ownership Reconciliation` queue with no automatic ownership mutation.

Hard constraints are introduced only after audit/migration readiness is proven.

**Coding approval status:** Governance prerequisites are closed. Schema/class/route design may now be frozen for implementation.

---

## 35. Change-control rule

Any future change to Ownership Reconciliation must be described before coding using:

```text
Actor
Case State
Asset(s)
Requested Action
Permission/Guard
Evidence Requirement
Canonical Data Changed
Transfer Plan
Next State
Profile Hold Effect
Lock Effect
Recalculation Effect
Deficiency Side Effect
Audit Event(s)
Rollback/Failure Behavior
```

This document is the architecture/business contract for the proposed Ownership Reconciliation domain and should be updated whenever one of these rules is changed or approved differently.
