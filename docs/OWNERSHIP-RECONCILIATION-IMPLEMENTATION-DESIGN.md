# Ownership Reconciliation — Implementation Design

> Baseline: Biawin CRM Integration `0.8.65.17`  
> Status: **DESIGN FROZEN FOR CODING — NOT IMPLEMENTED**  
> Depends on: `OWNERSHIP-RECONCILIATION-ARCHITECTURE-CONTRACT.md` (Governance GOV-01..GOV-05 APPROVED)

## 1. Goal

Implement the approved «تطبیق مالکیت» domain without adding ownership logic to Deficiency Workflow. Canonical owner changes must occur only through one transactional transfer service after case decision and, for high-risk cases, supervisor approval.

## 2. Migration strategy

Use the plugin's existing sequential, idempotent migration framework in `Biawin_CRM_Activator::MIGRATIONS` plus the full `activate()` schema ensure for fresh installs.

Recommended first implementation version after `0.8.65.17`: bump plugin/schema version and add one ownership migration entry. Do not overload an existing migration marker.

Migration order:

```text
M1  create ownership tables
M2  add canonical invoice phone columns (nullable initially)
M3  add roles/capabilities additively through capability ledger
M4  inventory legacy invoice-phone data; write audit summary only
M5  backfill clean/deterministic canonical invoice phone values
M6  mark ambiguous rows for Historical Ownership Reconciliation
M7  enable backend ownership guards
M8  only after acceptance/migration readiness: enforce exactly-one current invoice phone
```

No destructive migration may reassign an invoice, phone or customer automatically when evidence is ambiguous.

## 3. Schema changes

Table prefix below means `$wpdb->prefix . 'bcrm_'`.

### 3.1 `ownership_cases`

One formal dispute/review between a current-owner profile and claimant profile.

```text
id                         BIGINT UNSIGNED PK AI
case_code                  VARCHAR(40) UNIQUE NOT NULL
current_owner_customer_id  BIGINT UNSIGNED NOT NULL
claimant_customer_id       BIGINT UNSIGNED NOT NULL
status                     VARCHAR(40) NOT NULL
claim_reason               VARCHAR(120) NULL
request_note               LONGTEXT NULL
requested_by_user_id       BIGINT UNSIGNED NOT NULL
assigned_to_user_id        BIGINT UNSIGNED NULL
assigned_by_user_id        BIGINT UNSIGNED NULL
risk_level                 VARCHAR(20) NOT NULL DEFAULT 'NORMAL'
risk_reasons_json          LONGTEXT NULL
risk_evaluated_at          DATETIME NULL
decision                   VARCHAR(40) NULL
decision_method            VARCHAR(60) NULL
decision_note              LONGTEXT NULL
decided_by_user_id         BIGINT UNSIGNED NULL
decided_at                 DATETIME NULL
supervisor_approval_status VARCHAR(20) NOT NULL DEFAULT 'NOT_REQUIRED'
supervisor_user_id         BIGINT UNSIGNED NULL
supervisor_note            LONGTEXT NULL
supervisor_decided_at      DATETIME NULL
submitted_at               DATETIME NULL
assigned_at                DATETIME NULL
review_started_at          DATETIME NULL
closed_at                  DATETIME NULL
cancelled_at               DATETIME NULL
created_at                 DATETIME NOT NULL
updated_at                 DATETIME NOT NULL
lock_version               BIGINT UNSIGNED NOT NULL DEFAULT 1
```

Indexes:

```text
UNIQUE(case_code)
KEY(status)
KEY(risk_level,status)
KEY(current_owner_customer_id,status)
KEY(claimant_customer_id,status)
KEY(assigned_to_user_id,status)
KEY(supervisor_approval_status,status)
```

Allowed `status` values in service validation:

```text
DRAFT
SUBMITTED
ASSIGNED
UNDER_REVIEW
AWAITING_EVIDENCE
AWAITING_CUSTOMER_CONFIRMATION
AWAITING_SUPERVISOR_APPROVAL
DECIDED
CLOSED
CANCELLED
```

### 3.2 `ownership_case_assets`

One row per disputed/claimed invoice or phone. Do not infer sibling invoices from a shared phone.

```text
id                         BIGINT UNSIGNED PK AI
case_id                    BIGINT UNSIGNED NOT NULL
asset_type                 VARCHAR(20) NOT NULL       # INVOICE | PHONE
asset_id                   BIGINT UNSIGNED NOT NULL
source_customer_id         BIGINT UNSIGNED NOT NULL
target_customer_id         BIGINT UNSIGNED NOT NULL
requested_action           VARCHAR(40) NOT NULL       # TRANSFER | REVIEW_ONLY
requested_phone_resolution VARCHAR(40) NULL
requested_phone_id         BIGINT UNSIGNED NULL
requested_phone_normalized VARCHAR(30) NULL
final_action               VARCHAR(40) NULL
final_phone_resolution     VARCHAR(40) NULL
final_phone_id             BIGINT UNSIGNED NULL
final_phone_normalized     VARCHAR(30) NULL
specialist_override_reason LONGTEXT NULL
snapshot_json              LONGTEXT NOT NULL
decision                   VARCHAR(40) NULL
created_by_user_id         BIGINT UNSIGNED NOT NULL
created_at                 DATETIME NOT NULL
updated_at                 DATETIME NOT NULL
```

Phone-resolution values:

```text
KEEP_CURRENT_PHONE
USE_TARGET_PROFILE_PHONE
CLAIM_CURRENT_PHONE
PROPOSE_NEW_PHONE
```

Indexes/constraints:

```text
UNIQUE(case_id,asset_type,asset_id)
KEY(asset_type,asset_id)
KEY(source_customer_id)
KEY(target_customer_id)
```

Application invariant: an invoice may participate in only one active ownership case at a time. Implement with transactional conflict check; if a generated active-lock key/table is later preferred, add it separately rather than relying on an unsafe nullable unique trick.

### 3.3 `ownership_case_amendments`

Formal post-submit scope expansion/change record.

```text
id                    BIGINT UNSIGNED PK AI
case_id               BIGINT UNSIGNED NOT NULL
amendment_type        VARCHAR(40) NOT NULL      # ADD_ASSET | CHANGE_SCOPE | OTHER
reason                LONGTEXT NOT NULL
before_snapshot_json  LONGTEXT NOT NULL
after_snapshot_json   LONGTEXT NOT NULL
created_by_user_id    BIGINT UNSIGNED NOT NULL
created_at            DATETIME NOT NULL
```

Any amendment that adds a new asset forces `risk_level=HIGH_RISK` and emits an immutable event.

### 3.4 `ownership_locks`

Persistent lock of canonical asset ownership. This is different from temporary profile hold.

```text
id                    BIGINT UNSIGNED PK AI
entity_type           VARCHAR(20) NOT NULL      # INVOICE | PHONE
entity_id             BIGINT UNSIGNED NOT NULL
customer_id           BIGINT UNSIGNED NOT NULL
lock_reason           VARCHAR(50) NOT NULL
locked_by_event       VARCHAR(80) NULL
locked_at             DATETIME NOT NULL
released_at           DATETIME NULL
released_by_case_id   BIGINT UNSIGNED NULL
release_reason        VARCHAR(80) NULL
created_at            DATETIME NOT NULL
```

Indexes:

```text
KEY(entity_type,entity_id,released_at)
KEY(customer_id,released_at)
```

Service invariant: at most one active lock per asset. Enforce under transaction/row lock in the Lock Service.

### 3.5 `customer_holds`

Temporary global read-only hold for both profiles after case submit.

```text
id              BIGINT UNSIGNED PK AI
customer_id     BIGINT UNSIGNED NOT NULL
hold_type       VARCHAR(40) NOT NULL DEFAULT 'OWNERSHIP_REVIEW'
case_id         BIGINT UNSIGNED NOT NULL
status          VARCHAR(20) NOT NULL DEFAULT 'ACTIVE'
created_by      BIGINT UNSIGNED NOT NULL
created_at      DATETIME NOT NULL
released_by     BIGINT UNSIGNED NULL
released_at     DATETIME NULL
release_reason  VARCHAR(120) NULL
```

Indexes:

```text
KEY(customer_id,status)
KEY(case_id,status)
```

All ordinary mutation entry points must call `CustomerHoldService::assert_mutable($customer_id, $context)` before writing.

### 3.6 `ownership_events`

Append-only ownership ledger. Never update/delete through normal workflow.

```text
id             BIGINT UNSIGNED PK AI
case_id        BIGINT UNSIGNED NOT NULL
customer_id    BIGINT UNSIGNED NULL
asset_type     VARCHAR(20) NULL
asset_id       BIGINT UNSIGNED NULL
event_type     VARCHAR(80) NOT NULL
actor_user_id  BIGINT UNSIGNED NULL
meta_json      LONGTEXT NULL
created_at     DATETIME NOT NULL
```

Indexes:

```text
KEY(case_id,id)
KEY(customer_id,created_at)
KEY(asset_type,asset_id,created_at)
KEY(event_type,created_at)
```

### 3.7 `ownership_otp_challenges`

```text
id                  BIGINT UNSIGNED PK AI
case_id             BIGINT UNSIGNED NOT NULL
phone_asset_id      BIGINT UNSIGNED NULL
customer_id         BIGINT UNSIGNED NULL
phone_normalized    VARCHAR(30) NOT NULL
purpose             VARCHAR(50) NOT NULL
otp_hash            VARCHAR(255) NOT NULL
expires_at          DATETIME NOT NULL
attempt_count       SMALLINT UNSIGNED NOT NULL DEFAULT 0
max_attempts        SMALLINT UNSIGNED NOT NULL DEFAULT 5
sent_at             DATETIME NOT NULL
verified_at         DATETIME NULL
consumed_at         DATETIME NULL
provider_meta_json  LONGTEXT NULL
created_by_user_id  BIGINT UNSIGNED NOT NULL
created_at          DATETIME NOT NULL
```

OTP is only for the exact disputed phone under the approved policy. Successful OTP does not mutate ownership.

### 3.8 Canonical invoice phone

Current schema allows multiple rows in `invoice_phones`. Approved business rule is exactly one phone per invoice.

For compatibility, add nullable columns to `invoices` first:

```text
canonical_phone_normalized VARCHAR(30) NULL
canonical_phone_source     VARCHAR(30) NULL   # LEGACY | IMPORT | OWNERSHIP_CASE | MANUAL_VERIFIED
canonical_phone_updated_at DATETIME NULL
canonical_phone_case_id    BIGINT UNSIGNED NULL
```

During transition:

- `invoice_phones` remains legacy/history-compatible input/read support;
- new ownership code reads/writes the canonical invoice phone through a dedicated service;
- writes mirror the canonical relation into `invoice_phones` for legacy consumers until all reads are migrated;
- never erase old phone evidence just to satisfy the new current binding;
- after migration readiness, canonical phone becomes required by service invariant and later DB-level enforcement may be introduced in a separate migration.

This separates **invoice contact** from **phone profile ownership**.

## 4. Reuse existing tables

### `attachments`

Reuse existing attachment infrastructure:

```text
object_type = 'ownership_case'
object_id   = ownership_cases.id
file_kind   = 'ownership_evidence' | 'claimant_receipt' | 'consent_document' | ...
```

No new evidence blob table is required for MVP.

### `activities`

Write concise customer-facing timeline summaries for both profiles, but authoritative detailed history remains `ownership_events`.

## 5. Roles/capabilities

Add through existing capability ledger; never recreate deleted roles or overwrite explicit denials.

Recommended roles:

```text
bcrm_ownership_specialist   کارشناس تطبیق مالکیت
bcrm_ownership_supervisor   سرپرست تطبیق مالکیت
```

Capabilities:

```text
submit_ownership_claim
view_own_ownership_claims
view_ownership_cases
assign_ownership_cases
review_ownership_cases
amend_ownership_case
request_ownership_evidence
record_ownership_contact
issue_ownership_otp
verify_ownership_otp
approve_normal_ownership_transfer
approve_high_risk_ownership_transfer
reject_ownership_claim
record_ownership_withdrawal
manage_ownership_evidence
view_ownership_audit
manage_ownership_policy
```

`approve_high_risk_ownership_transfer` belongs to supervisor/admin policy, not ordinary specialist.

## 6. Required classes

Do not implement ownership logic inside Deficiency Workflow.

### Core domain

`includes/class-biawin-crm-ownership-repository.php`
: SQL reads/writes for ownership tables only. No business decisions.

`includes/class-biawin-crm-ownership-workflow.php`
: legal state transitions, draft/submit/assign/review/wait/decision/close/cancel.

`includes/class-biawin-crm-ownership-risk-service.php`
: deterministic `NORMAL/HIGH_RISK` evaluation, persisted reasons, escalation; no manual downgrade.

`includes/class-biawin-crm-ownership-lock-service.php`
: persistent invoice/phone locks; guards all owner-changing paths.

`includes/class-biawin-crm-customer-hold-service.php`
: global read-only hold check/apply/release for both profiles.

`includes/class-biawin-crm-ownership-transfer-service.php`
: the only service allowed to change canonical invoice/phone ownership after approved decision. Transactional, revalidates locks/owners/version, moves invoice-linked services, writes ledger.

`includes/class-biawin-crm-invoice-phone-service.php`
: canonical exactly-one invoice phone resolution plus legacy mirror/history compatibility.

`includes/class-biawin-crm-ownership-authorization.php`
: capability + assignment + case-state + risk/SoD checks.

`includes/class-biawin-crm-ownership-otp.php`
: issue/verify/consume challenge for disputed phone only; no ownership mutation.

`includes/class-biawin-crm-ownership-audit.php`
: append-only event writer and customer timeline bridge.

`includes/class-biawin-crm-ownership-migration-audit.php`
: Phase-0 legacy data inventory/classification: clean / deterministic / ambiguous.

### Workspace/controllers

`admin/class-biawin-crm-ownership-admin.php`
: admin/workspace page routing, filters, assignment, supervisor queue.

Optional frontend controller if ownership workspace belongs to portal UI rather than wp-admin:

`includes/class-biawin-crm-ownership-workspace.php`

UI controllers must call domain services; they must never update `invoices.customer_id` or `phones.customer_id` directly.

## 7. Existing code integration points that must be guarded

Before UI implementation, inventory every path that can mutate:

```text
invoices.customer_id
phones.customer_id
invoice phone relation
profile merge/collapse
invoice-linked services.customer_id
```

At minimum, existing attach/merge/import/AJAX/REST/admin-post/internal helper routes must call:

```text
CustomerHoldService::assert_mutable(customer_id, context)
OwnershipLockService::assert_can_change_invoice_owner(invoice_id, context)
OwnershipLockService::assert_can_change_phone_owner(phone_id, context)
```

Ownership Transfer Service uses a privileged internal context token/call path so an approved case can mutate while ordinary held-profile operations remain blocked. This bypass must not be generally callable from controllers.

## 8. Submit transaction

Formal submit must atomically:

```text
1 validate draft + two distinct profiles
2 validate selected assets and current owners
3 block conflicting active invoice case
4 snapshot both profiles and assets
5 compute/persist risk
6 set case SUBMITTED
7 create ACTIVE holds for both profiles
8 write CASE_SUBMITTED + PROFILE_HOLD_APPLIED events
```

If any step fails, no hold/case-state partial write remains.

## 9. Amendment transaction

Specialist can expand scope after submit only by:

```text
1 verify assigned specialist/authorized supervisor
2 capture before snapshot
3 validate new asset/current owner/conflicts
4 insert amendment + asset
5 force HIGH_RISK
6 persist risk reasons
7 capture after snapshot
8 write CASE_AMENDED / ASSET_ADDED / RISK_ESCALATED events
```

## 10. Decision and approval routing

### Normal case

```text
UNDER_REVIEW
→ specialist decision
→ re-evaluate risk
→ if NORMAL and TRANSFER_APPROVED: DECIDED
→ execute Transfer Service
→ post-process/recalculate
→ release holds
→ CLOSED
```

### High-risk case

```text
UNDER_REVIEW
→ specialist proposed TRANSFER_APPROVED
→ re-evaluate risk = HIGH_RISK
→ AWAITING_SUPERVISOR_APPROVAL
→ supervisor approve
→ DECIDED
→ execute Transfer Service
→ post-process/recalculate
→ release holds
→ CLOSED
```

Supervisor rejection must be audited and return the case to `UNDER_REVIEW` (with reason) rather than execute or silently close it.

## 11. Transfer transaction

Inside one DB transaction where supported by the involved tables:

```text
1 SELECT/FOR UPDATE case and relevant asset/owner rows
2 verify case state + approval requirements
3 verify source/target still match snapshots/current plan
4 verify ownership locks/active holds
5 apply invoice owner changes explicitly per asset
6 resolve exactly one canonical invoice phone per moved invoice
7 apply phone owner transfer only when final plan explicitly says so
8 move invoice-linked services with the invoice owner
9 close/recreate persistent ownership locks for moved assets
10 append immutable transfer events
11 increment lock_version / mark execution complete
12 COMMIT
```

Never infer sibling invoice transfer from a shared phone.

After commit:

```text
13 recalculate source profile
14 recalculate target profile
15 if source becomes incomplete, invoke normal Deficiency routing
16 write recalculation/Deficiency events
17 release both customer holds only after safe post-processing
18 close case
```

If post-processing fails after the canonical transfer committed, do **not** reverse ownership silently. Mark execution/post-processing failure, keep holds, alert supervisor/admin, and resume idempotently.

## 12. Lock creation on completed profiles

When a profile becomes complete through the existing canonical completion/recalculation path, ensure an active persistent ownership lock exists for every invoice currently owned by that profile.

This hook must be idempotent. It must not create duplicate active locks and must not unlock an invoice merely because the profile later becomes incomplete. Unlock/relock occurs only through approved ownership transfer or separately governed policy.

## 13. Historical migration audit

The audit command/service must produce counts and identifiers for:

```text
invoice with 0 invoice phones
invoice with exactly 1 phone
invoice with >1 phones
phone normalization ambiguity
invoice owner/profile mismatch candidates
service.customer_id != invoice.customer_id
completed-profile invoices needing initial lock
legacy route capable of bypassing lock/hold guard
```

Classification:

```text
CLEAN
DETERMINISTIC_REPAIR
AMBIGUOUS_REVIEW
```

`AMBIGUOUS_REVIEW` records enter Historical Ownership Reconciliation; no automatic owner mutation.

## 14. Minimum implementation tests before runtime rollout

In addition to the architecture contract acceptance suite, coding must include focused tests for:

- risk engine trigger matrix and no-downgrade rule;
- specialist normal approval vs supervisor high-risk approval;
- global read-only holds across all discovered mutation routes;
- amendment automatically escalates risk;
- OTP sent only to exact disputed phone and never mutates ownership;
- transaction rollback on pre-commit failure;
- idempotent post-commit recovery without double transfer;
- lock persistence when a formerly complete profile later becomes incomplete;
- exactly one canonical phone after every approved invoice move;
- legacy mirror does not create multiple canonical phones;
- no source-profile auto-collapse;
- Deficiency routing after recalculation mismatch.

## 15. Coding order

```text
C1 schema + migration audit + capabilities
C2 repository + audit/event writer
C3 customer hold + ownership lock guards
C4 patch all existing mutation entry points to fail closed
C5 draft/submit + attachments/evidence
C6 assignment/workspace + split profile view
C7 risk engine + amendments
C8 decision/SoD + transfer service + invoice phone service
C9 recalculation/Deficiency integration
C10 OTP
C11 legacy data migration/backfill
C12 browser/runtime acceptance + regression
C13 only then consider hard DB enforcement of canonical phone non-null/uniqueness semantics
```

Backend guards (C3/C4) are the first behavior-changing safety milestone. Do not ship claim UI before guards exist.

## 16. Non-goals for first implementation

- automatic merge of the two profiles;
- automatic transfer of all invoices sharing a phone;
- OTP as sole proof of invoice ownership;
- deleting prior invoice-phone history;
- auto-collapse of source profile;
- mutating canonical ownership from Draft/Submit;
- silently repairing ambiguous historical ownership.
