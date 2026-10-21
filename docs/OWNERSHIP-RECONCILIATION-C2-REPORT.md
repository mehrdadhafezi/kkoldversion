# Ownership Reconciliation C2 backend report

## 1. Baseline and scope

- Branch: `feature/ownership-reconciliation`.
- Starting HEAD/C1 commit: `991c3bac815c9fe153096831107441f2194ed632` (`991c3ba`). Working tree was clean.
- Pre-feature baseline: `14cccf90850f5153db0ed08b0103de92fc09bac0`, tag `baseline-v0.8.65.17-ownership-approved`.
- Version before/after: **0.8.65.18 -> 0.8.65.19**.
- Implemented backend callable services only. No ownership HTML, dashboard, forms, CSS, JS, REST/AJAX endpoint additions, commit, push or deployment.
- Consulted STRUCTURE.md, the Deficiency/Finance state-machine document, approved ownership architecture contract, frozen implementation design, C1 report and C1 code/tests. Existing mutation paths were inspected across customer/import/workflow/finance/REST/admin/frontend/reconstruction services.

## 2. Necessary schema/version changes

Five additive columns on `ownership_cases`:

```text
snapshot_json       LONGTEXT NULL
execution_state     VARCHAR(30) NOT NULL DEFAULT 'NOT_STARTED'
execution_error     LONGTEXT NULL
executed_at         DATETIME NULL
finalization_json   LONGTEXT NULL
```

They persist the immutable two-profile submission snapshot, distinguish committed canonical transfer from unfinished finalization, and checkpoint source/target recalculation. The case workflow status remains the approved vocabulary; execution substates are `NOT_STARTED`, `COMMITTED`, `FINALIZATION_FAILED`, `FINALIZED`.

Added migration `0.8.65.19 => migrate_0865_19`, using the existing retryable schema ensure. New ownership tables explicitly request InnoDB. No engine conversion, historical cleanup, owner backfill, hard canonical-phone NOT NULL constraint, or new table was added. Existing C1 tables/indexes and nullable canonical invoice fields remain.

The upgrade from the supplied C1 baseline performs only additive ownership DDL and does not replay legacy activation's assignment repairs/Deficiency backfill. Migration selection now excludes versions above the running plugin version; this also preserves the original C1 test's version-scoped behavior. The current version is stamped only after success. Role marker 9 and C1 capability mappings are unchanged.

## 3. New classes

| Class file in includes/ | Responsibility |
|---|---|
| class-biawin-crm-ownership-repository.php | Checked reads/writes, transactions, engine validation, scoped named serialization gate, optimistic case versions and active-asset conflicts |
| class-biawin-crm-ownership-workflow.php | Draft/submit, immutable snapshots, assignment/review/wait, evidence/contact, amendments/final plans, decisions/cancellation, scoped list/get |
| class-biawin-crm-ownership-risk-service.php | Deterministic risk evaluation with persisted sticky escalation reasons |
| class-biawin-crm-customer-hold-service.php | Both-profile holds, ordinary mutability checks, case-scoped release |
| class-biawin-crm-ownership-lock-service.php | Persistent asset ownership/binding locks, deterministic completion synchronization |
| class-biawin-crm-invoice-phone-service.php | Current canonical contact resolution, validated final phone modes and append-only legacy mirror |
| class-biawin-crm-ownership-otp.php | Exact disputed-phone challenges, secure hashes, delivery adapter, scoped verification/consumption/expiry/limits |
| class-biawin-crm-ownership-transfer-service.php | Approved canonical transfer transaction and resumable post-commit finalization; private execution context |
| class-biawin-crm-ownership-audit.php | Append-only detailed events and concise customer activity summaries |
| class-biawin-crm-ownership-write-guard.php | Central ordinary-write validation, including existing raw import SQL and media deletion protection |

Repository/schema/audit/lock/hold/risk/phone/write-gateway helpers are internal infrastructure. Future controllers must call the authenticated Workflow/OTP/Transfer application commands below rather than expose generic PHP method dispatch or internal persistence helpers.

## 4. Callable backend surface

All mutations derive the actor from the current WordPress session, check capability plus authoritative case scope, lock the case and require its current `lock_version`. Successful commands increment that version. Fetch the current case after mutations; stale versions fail closed. Domain denial/failure raises an exception except unsuccessful OTP verification, which returns false after persisting the attempt/audit.

Requester methods on `Biawin_CRM_Ownership_Workflow`:

- `create_draft(source, claimant, note, reason)`: validates two real, distinct profiles and existing access to the claimant profile; returns case ID.
- `update_draft(id, version, note, reason)`.
- `add_draft_asset(id, version, input)` / `remove_draft_asset(id, version, asset_row_id)`.
- `add_evidence(id, version, media_id, kind, note, conflicting)` for an owned allowed draft/active claim.
- `submit(id, version)`; `get(id)`; `list_cases(after_id, limit)`.

Asset input uses `asset_type` (`INVOICE`/`PHONE`), `asset_id`, optional `requested_action` (`TRANSFER` default or `REVIEW_ONLY`), and for invoices `phone_resolution`, `phone_id`, `phone`. Source/target ownership IDs and actor fields are loaded by the service, not trusted from input.

Specialist/supervisor methods on Workflow:

- `assign(id, version, specialist_user_id)`; `start_review(id, version)`.
- `request_evidence(id, version, note, customer_confirmation)`; `record_contact(id, version, result, note)`.
- `amend(id, version, asset_inputs, mandatory_reason)`.
- `set_final_plan(id, version, asset_row_id, plan, reason)`; plan supports `action`, `phone_resolution`, `phone_id`, `phone`.
- `evaluate_risk(id, version, optional_escalation_reason)`; no downgrade API exists.
- `decide(id, version, decision, verification_method, mandatory_note)`.
- `supervisor_decision(id, version, approve_bool, mandatory_note)`.
- `cancel(id, version, mandatory_reason)`; requester cancels only their own Draft, supervisor cancels an unexecuted active case with safe case-scoped hold release.

OTP: `Biawin_CRM_Ownership_OTP::issue(id, version, phone_asset_id, purpose)` returns challenge ID; `verify(id, version, challenge_id, phone_asset_id, purpose, code)` returns bool.

Execution: `Biawin_CRM_Ownership_Transfer_Service::execute(id, version)` commits eligible ownership once and immediately finalizes; `resume(id, current_version)` safely resumes committed post-processing. Repeated resume on an already finalized case is harmless; repeated execute cannot apply a second transfer.

Read payloads include case/assets/status/version and permitted evidence. Authorized reviewers also receive immutable snapshots, current profile/asset values and audit events when they retain audit capability. Requesters receive their own claim view without internal source snapshots, OTP/contact audit, recovery errors or other users' evidence. Listing is paginated and scoped to creator or assigned specialist; ownership supervisors have their approved broader scope.

## 5. State machine

```text
DRAFT -> SUBMITTED -> ASSIGNED -> UNDER_REVIEW
UNDER_REVIEW <-> AWAITING_EVIDENCE / AWAITING_CUSTOMER_CONFIRMATION
UNDER_REVIEW -> DECIDED -> CLOSED                    (withdrawal/rejection)
UNDER_REVIEW -> DECIDED -> execute/finalize -> CLOSED (NORMAL approval)
UNDER_REVIEW -> AWAITING_SUPERVISOR_APPROVAL
    -> DECIDED -> execute/finalize -> CLOSED          (independent HIGH_RISK approval)
    -> UNDER_REVIEW                                  (supervisor rejection with reason)
DRAFT / authorized unexecuted active state -> CANCELLED
```

Draft/Submit change no invoice owner, phone owner, service owner, canonical phone, customer completeness or Deficiency/Finance state. Submit atomically revalidates assets, captures snapshots, checks active invoice/phone conflicts, evaluates risk and applies both profile holds. Submitted cases are never physically deleted.

## 6. Risk and decisions

A single explicitly transferred invoice without phone transfer or material override is NORMAL. HIGH_RISK is sticky when applicable: disputed phone transfer, CLAIM_CURRENT_PHONE, multiple invoices in scope, post-submit expansion, material specialist override, conflicting evidence, failed/expired OTP or explicit escalation. Re-evaluation occurs on submission, relevant amendment/plan/evidence/OTP changes, decision and execution. Source incompleteness after transfer is not an escalation trigger.

Overrides require a reason and before/after event. Expansion requires an amendment with before/after snapshot and ASSET_ADDED/CASE_AMENDED events. Review-only assets are never silently transferred.

Approval requires documented supporting evidence. CLAIMANT_RECEIPT requires a claimant receipt. CURRENT_OWNER_CONSENT requires an audited contact with result `current_owner_consent` and note. OTP verification remains supplementary evidence and cannot substitute for invoice ownership investigation. Evidence byte hashes are snapshotted in EVIDENCE_ADDED events and checked before approval and execution.

NORMAL can be approved by the assigned specialist. HIGH_RISK requires a separately recorded supervisor approval from a different actor than the proposing decision-maker, including if the proposer also has supervisor capabilities. Supervisor rejection preserves the case/history and returns it to UNDER_REVIEW.

## 7. Authorization matrix

| Actor | Request own claim | Review/normal approval | Assignment | High-risk second approval |
|---|---|---|---|---|
| bcrm_agent, bcrm_supervisor, bcrm_deficiency_agent, bcrm_deficiency_supervisor | Yes, existing claimant-profile access required | No | No | No |
| Assigned ownership specialist | Own request under existing profile access; assigned review scope | Yes, within capabilities/state | No by default | No |
| Ownership supervisor / portal admin / administrator | Yes | Approved broader scope | Yes | Yes, different actor required |

C1 role/capability separation and the provisioning ledger are preserved, including explicit denials, removed capabilities and deleted roles. No request-supplied actor or owner ID overrides server state.

## 8. Holds and persistent locks

Customer Hold Service is centralized. Existing plugin CRUD/raw mutations delegate to `Biawin_CRM_DB::write()` and resolve affected current and prospective customer/asset ownership before the original SQL executes. Customer-service entry points and receipt promotion/transition entry points also validate holds before side effects. Both source and target are checked; reads remain available.

Different non-conflicting asset cases may share profiles. Holds belong to each case; closing/cancelling one cannot release another case's hold. Ordinary edits, invoice/phone attach, invoice creation/link, phone identity changes, merge/collapse, imports, assignments, service/customer mutations, receipt review and Deficiency/Finance transitions fail closed when their profile is held. Existing workflow records are preserved. Customer-facing reads skip their previous write-on-read recalculation/primary-phone repair while held and return persisted values.

Persistent invoice locks are independent of current completeness. Completion synchronization is explicit/per-profile, not a global request-time backfill. It creates locks only when the invoice contact is deterministic or already canonical; ambiguity generates LEGACY_LOCK_REVIEW_REQUIRED. Locks do not disappear when a profile later becomes incomplete. Transfer closes old locks and creates destination locks transactionally. Guards protect owner changes/deletions, invoice phone binding/history changes and locked phone identity renaming; harmless metadata writes remain possible outside holds.

### Explicit historical readiness service

`Biawin_CRM_Ownership_Lock_Service::sync_initial_batch($after_id = 0, $limit = 200)` requires the current actor's `manage_ownership_policy`. Invoke it from a bootstrapped WordPress backend/CLI session as an authorized supervisor during local/staging rollout. Nothing calls it automatically; no route, scheduler or full-table request-time migration was added.

```php
$report = Biawin_CRM_Ownership_Lock_Service::sync_initial_batch($saved_cursor, 200);
// Persist records and next_after_id after each call.
// Correct report.error if present, then retry from next_after_id.
// Otherwise repeat while has_more is true.
```

Keyset pagination selects at most limit+1 invoice IDs (limit bounded to 1..1000). Each invoice is re-read and processed in its own gated transaction. C1's full historical classifier is reused: CLEAN/DETERMINISTIC_REPAIR invoices with complete owners receive only a missing persistent lock and event. Invoice, canonical/legacy phone, owner and service data are never repaired. Incomplete profiles get no new lock. Multiple/invalid contacts, missing/conflicting identities, missing owners, canonical conflicts and service-owner mismatches return `HISTORICAL_REVIEW_REQUIRED` with issue codes, without guessing or creating unsafe locks.

Record statuses: `LOCK_CREATED`, `ALREADY_LOCKED`, `INCOMPLETE_SKIPPED`, `HISTORICAL_REVIEW_REQUIRED`. Existing active locks and their timestamps/provenance/events remain unchanged. The response contains `records`, `next_after_id`, `has_more`, `failed_invoice_id`, `error`. A failure rolls back that invoice's lock/event, stops the page and leaves the cursor before it; earlier commits survive and retry is idempotent. Persist review records separately: cursor progress is not a historical readiness approval or a repair queue. Rerun from zero after skipped records become eligible or rollout data changes; pagination is not a frozen global snapshot.

## 9. Transfer, phone binding and recovery

A database/prefix-scoped MySQL named gate serializes ownership commands and the plugin's existing guarded mutations. Legacy transactions now acquire the gate only at their first protected mutation, retaining it until COMMIT/ROLLBACK. Unrelated staging/catalog transactions and plain wpdb INSERT of a new customer acquire no gate. INSERT cannot overwrite an existing identity; duplicate-key false returns remain unchanged. Early hold assertions still protect bundles creating new profiles from held sources. Guard row/parent/hold/lock reads and customer-deletion asset enumeration use current locking reads after delayed acquisition in legacy transactions, avoiding stale repeatable-read snapshots. Named lock unavailability fails closed. Ownership commands validate required tables are InnoDB, reject nested transactions and require an autocommit connection. SET TRANSACTION READ WRITE, without SESSION, is used before START TRANSACTION to reject an external active transaction rather than implicitly commit it; no MariaDB-only transaction variable is used.

Within the ownership transaction: lock/re-read case and selected assets; check optimistic version, legal decision/risk/independent approval, both holds, active asset conflicts, request-time owner/phone/amount/service snapshots and evidence hashes; build every invoice phone plan; update only explicit TRANSFER assets; align invoice-linked services; update lock history; append before/after events; mark execution COMMITTED. Any pre-commit failure rolls all of that back.

Phone modes:

- KEEP_CURRENT_PHONE: same invoice contact, phone profile owner unchanged unless a separate explicit phone transfer is approved.
- USE_TARGET_PROFILE_PHONE: selected existing phone must still belong to claimant.
- CLAIM_CURRENT_PHONE: requires that exact phone as an explicit TRANSFER asset, source ownership revalidation and high-risk approval.
- PROPOSE_NEW_PHONE: normalize/validate at review, create the destination phone only during approved execution; an existing source-owned phone additionally requires an explicit approved PHONE transfer asset; ownership belonging to a third profile requires a separate case with the correct parties.

Each moved invoice receives one canonical normalized phone and OWNERSHIP_CASE provenance. The legacy invoice_phones mirror is inserted only when absent; prior rows are not deleted. No sibling invoice is inferred from a shared phone. Payments are untouched and remain attached to invoice IDs. Source profiles are never collapsed.

After commit, source and target recalculation each run in a separate transaction with their own durable checkpoint. The existing Customer/Workflow/Deficiency services perform normal financial reconciliation and case routing. Failure leaves ownership committed, keeps both holds ACTIVE, persists FINALIZATION_FAILED/error and CASE_EXECUTION_FAILED, and permits an authorized resume without repeating canonical transfer. Only successful finalization releases this case's holds and closes it.

## 10. OTP and evidence lifecycle

OTP is exact-case/phone/purpose scoped, uses a secure password hash, five-minute expiry, five attempts, one-time verification/consumption and a 60-second resend throttle. Superseded challenges are consumed. OTP deadlines/sent_at use UTC. Plaintext is neither persisted nor returned/audited. Wrong, expired, mismatched, stale or consumed challenges cannot provide reusable transfer authority. Verification changes no canonical ownership.

A site adapter must implement the WordPress filter `biawin_crm_ownership_send_otp` with arguments `(accepted, normalized_phone, plaintext_code, case_id, purpose)` and return strict true only after provider acceptance. The default returns false, consumes the undelivered challenge and records failure; no real SMS provider was configured or contacted here. Delivery is an external side effect: a later DB rollback may leave a delivered but unusable code; it cannot authorize ownership mutation.

OTP hardening explicitly checks accepted delivery metadata at verification and OTP-based approval. Approval also requires the exact disputed case/phone, supported purpose, a successful attempt within its budget, and verified_at between sent_at and expiry. Missing adapter, undelivered challenge, delivery alone, or verification timestamps without accepted delivery cannot approve a phone claim. Successful verification still requires supporting evidence and independent high-risk approval. Invoice-only receipt/document flows remain independent of SMS configuration. Adapter acceptance is a trusted transport assertion; successful code verification proves control. A vendor delivery-receipt integration was not added.

Evidence reuses existing WordPress media plus bcrm_attachments with object_type ownership_case. Registration checks media access/file type, appends a new relation and file hash event; no overwrite/delete API is provided. Evidence changes after decision are refused. WordPress media deletion is blocked for ownership evidence; held ordinary receipt media deletion is also protected. External filesystem edits are detected by hash validation rather than silently accepted.

## 11. Existing files patched and regression safeguards

- Bootstrap: class includes, version, evidence media deletion filter.
- Activator/schema: additive migration and five fields; future-version migration exclusion; guarded legacy assignment-repair update.
- DB: centralized write entry, early customer guard and held-read refresh helper.
- Customer Service: write delegation and early hold checks, preventing partial bundles/new-profile creation against a held source.
- Shared Workflow Service: write delegation and the completion lock hook only.
- Deficiency/Finance/receipt staging: write delegation and early mutation/promotion hold checks. Read routes are unchanged.
- REST, Admin, Frontend, After-Sales Config, Import, Refund Register, Controlled Reconstruction and Blacklist Final Commit: existing write calls now delegate to the centralized guard; other business logic is preserved. Frontend additionally wraps its existing refresh calls so held profiles remain readable; no HTML/CSS/JS/layout was edited.
- Structure/state-machine docs: appended integration notes; original Deficiency/Finance state/transition descriptions remain.
- Changelog/readme: version metadata.

The gateway supports the repository's existing CRUD signatures and single-tuple raw upsert/update/join-update/delete forms. It resolves prospective values with SQL rather than trusting input owner IDs. Previously unseen canonical mutation SQL fails closed until explicitly mapped. Ordinary audit logging and unrelated staging/catalog tables remain outside customer ownership enforcement. Internal new ownership writes go through the dedicated services, not the ordinary mutation gateway. Its private transfer context is not caller-supplied.

The gateway adds validation SELECTs and serializes protected mutations; it does not claim identical latency/concurrency characteristics. Unrelated transactions and plain new-profile inserts bypass the gate. Protected mutations still acquire it when an initial check would show no hold/lock: otherwise a new submission/hold or lock could race between that check and the original write. This retains the existing coordination design without introducing a new per-profile locking architecture. Read-refresh callbacks retain their gate because they perform protected multi-step writes. MySQL load/concurrency acceptance is required before production rollout. Outside applicable holds/locks, original SQL, arguments, return values and business transitions are retained; this is supported by static inverse-delegation comparison and SQL-backed guarded-vs-C1 behavior tests, not a claim of completed live regression acceptance.

### Exact retained legacy mutation files and necessity

The Production Readiness file was restored entirely to C1. After-Sales catalog/unit edits and catalog transaction calls were restored; only complaint-inquiry insertion remains guarded there. All 13 retained service/controller files preserve exact C1 business bodies after inverse delegation/removal of the specified hooks; no formatting-only or line-ending-only diff remains.

| Repository-relative file | Why its modification is necessary |
|---|---|
| admin/class-biawin-crm-admin.php | Admin customer/phone/invoice/assignment mutations must honor the shared hold/lock boundary. Existing handlers/responses remain. |
| includes/class-biawin-crm-after-sales-config.php | Only complaint-inquiry insertion needs its complaint customer's hold checked. Unrelated configuration changes were restored. |
| includes/class-biawin-crm-blacklist-final-commit-engine.php | Destructive blacklist canonical writes and their transaction boundaries must honor holds/locks. |
| includes/class-biawin-crm-controlled-reconstruction.php | Raw/CRUD reconstruction must not reassign/remove held or locked canonical records. |
| includes/class-biawin-crm-customer-service.php | Authoritative attach/merge/manual/phone mutations and early hold checks before partial bundles/new-profile side effects. |
| includes/class-biawin-crm-deficiency-workflow.php | Transitions/tags/assignments/documents must honor holds; process_action checks before side effects. Transition logic remains unchanged. |
| includes/class-biawin-crm-finance-review.php | Receipt reviews and finalization must honor the case customer's hold, including checks before promotion/transition. |
| includes/class-biawin-crm-frontend.php | Direct customer/status writes require guarding; read-refresh wrappers retain held profile readability. No layout/markup changes. |
| includes/class-biawin-crm-import-service.php | Import upserts, identity reconciliation and canonical writes must not bypass holds/locks; transactions retain a gate only after protected mutations. |
| includes/class-biawin-crm-receipt-staging.php | Resolve the case customer/hold before file promotion and guard authoritative receipt/transition writes. |
| includes/class-biawin-crm-refund-register.php | Refund records and customer tags are customer mutations subject to holds. |
| includes/class-biawin-crm-rest.php | Existing interaction/customer writes need the shared guard; routes/payloads/business logic remain. |
| includes/class-biawin-crm-workflow-service.php | Shared customer/workflow writes need guarding; the existing completion point creates persistent locks. |

Other existing PHP integration files still modified: `includes/class-biawin-crm-db.php` provides gateway/early/read-refresh helpers; `includes/class-biawin-crm-activator.php` runs retryable additive C2 DDL without legacy repair replay and guards its canonical repair update; `includes/class-biawin-crm-ownership-schema.php` defines execution fields/InnoDB; `includes/class-biawin-crm-ownership-authorization.php` adds session/case-scope helpers; `biawin-crm-integration.php` loads services, protects evidence media and increments the version. Remaining existing non-PHP changes are documentation/version metadata: CHANGELOG.md, STRUCTURE.md, docs/DEFICIENCY-FINANCE-STATE-MACHINE.md, readme.txt.

Behavioral evidence composes with the source audit: hardening tests load the actual committed C1 DB/Customer/Workflow/Deficiency classes under separate names and compare successful recalculation/Deficiency routing, unlocked invoice attachment, phone verification and journey transitions against C2 on identical SQL fixtures. A 22-table matrix compares CRUD/raw UPDATE/DELETE business DML, wpdb data/format/where arguments, return values, every resulting table row, insert_id/rows_affected/last_error, duplicate-key false returns and throwing-driver exceptions. The complete C2 scenarios also exercise holds, transaction rollback/recovery and existing transitions. Guard SELECTs are expected additions; full query traces/latency are not claimed identical. Every WordPress endpoint/filesystem branch and MySQL dialect is not exhaustively executed here.

## 12. Verification actually executed

**Test-double PASS**:

```text
php -n tests/ownership-c1.php
PASS: 179 C1 checks

php -n -d extension_dir=C:/php-8.4.4/ext -d extension=pdo_sqlite tests/ownership-c2.php
PASS: 304 SQL-backed C2 checks
```

Added hardening coverage: all readiness cases (clean/deterministic, repeat, incomplete, ambiguous contacts/service owner, unchanged existing lock, injected event failure with lock rollback and retry); missing/undelivered/unverified/successful OTP approval boundaries and unaffected invoice-only flows; unrelated transaction/new-profile gate avoidance; deferred gate retention and current reads; actual C1-vs-C2 successful business flows; 22-table CRUD/raw SQL differential matrix and false/throw behavior.

C1 tests are unchanged. Their two intentional injected-failure error logs are expected assertions of retry safety, not failed tests.

The C2 suite executes actual new domain and existing Customer/Workflow/Deficiency code against a SQLite-backed wpdb/WordPress double. Real SQL rows, transactions, trigger-injected failures, held writes, state transitions, canonical transfer, recalculation and checkpoints are exercised. MySQL engine metadata, advisory locks and WordPress session/media/provider behavior are simulated, so this is not a MySQL or WordPress runtime claim.

Coverage includes: Draft/Submit canonical-data isolation; both holds; held mutation denial and unaffected unheld operations; profile read refresh; assigned scope/requester separation; NORMAL and HIGH_RISK decisions and independent supervisor; rejection/withdrawal/cancellation; amendments/override/sticky risk; exact OTP scope/hash/expiry/limits/throttle/reuse; all four phone modes, including an explicitly claimed source-owned proposed replacement; phone-only and sibling isolation; canonical phone/history; services/payments/profile retention; existing source/target recalculation and Deficiency routing; lock persistence/identity/binding guards; pre-commit rollback and submit rollback; post-commit failure with retained holds; idempotent resume/no double execution; active-asset conflict; stale snapshots/versions; concurrent-case hold release isolation; evidence immutability; nontransactional engine rejection; external transaction nesting rejection; named lock failure.

**Static PASS**:

```text
python tests/ownership-c2-static.py
PASS: 14 legacy service/controller files retain business logic after guard/hook removal
PASS: 14 protected assets/auth/reporting/C1-test files unchanged from C1
PASS: approved C1 schema fields plus all five additive C2 fields present
PASS: PHP lint on all 53 PHP files
PASS: git diff --check
```

Deficiency/Finance constants, transitions, existing reporting, authentication, CSS/JS and layouts are protected by these source comparisons. C2 PHP uses syntax compatible with the plugin's PHP 7.4 minimum; actual lint/runtime here used PHP 8.4.4.

**Live DB BLOCKED**: no local WordPress installation or configured MySQL/MariaDB database exists in this checkout (no wp-config.php or wp-load.php). Real dbDelta repeat upgrades/fresh activation, stored WordPress roles, MySQL SQL-form compatibility, InnoDB rollback/deadlock behavior and parallel worker races/load remain unverified.

**Browser BLOCKED**: no running WordPress site was available. Existing browser regressions were not run; no ownership UI was created.

## 13. Known risks / deliberately deferred work

- Delayed gate acquisition can encounter lock-order contention if a legacy transaction already took row locks before its first protected mutation. Guard reads use current locking reads, and lock acquisition/read failure fails closed, but actual MySQL deadlock/timeout handling and caller rollback must be accepted under parallel load.
- Run real WordPress/MySQL acceptance, including concurrency and every discovered raw import/reconstruction SQL form, before production rollout. Named locks, InnoDB and normal autocommit are mandatory for ownership commands.
- Configure/test a real OTP sender adapter. No fake delivery success or provider credentials were introduced.
- Ambiguous legacy invoice contacts or invoice/service owner mismatches fail closed instead of choosing a phone. C1 audit remains read-only. Explicit historical initial-lock synchronization is implemented below; historical repair/queue UI remains deferred.
- SQL guard coverage is for plugin mutations routed through the central DB helper; unrelated third-party direct SQL, external database access and filesystem administration are outside its enforcement boundary. Future controllers must use the documented application commands; new canonical raw SQL forms need explicit mapping.
- UI, HTTP controllers, browser polish, operator notification transport and nonessential historical cleanup are deferred. Failures are visible through backend execution_state/error and authoritative events.
- No unresolved approved business-rule contradiction was found. Infrastructure dependencies and unexecuted acceptance tests above remain material rollout limits.

## 14. Files changed

- `CHANGELOG.md`
- `STRUCTURE.md`
- `admin/class-biawin-crm-admin.php`
- `biawin-crm-integration.php`
- `docs/DEFICIENCY-FINANCE-STATE-MACHINE.md`
- `docs/OWNERSHIP-RECONCILIATION-C2-REPORT.md`
- `includes/class-biawin-crm-activator.php`
- `includes/class-biawin-crm-after-sales-config.php`
- `includes/class-biawin-crm-blacklist-final-commit-engine.php`
- `includes/class-biawin-crm-controlled-reconstruction.php`
- `includes/class-biawin-crm-customer-hold-service.php`
- `includes/class-biawin-crm-customer-service.php`
- `includes/class-biawin-crm-db.php`
- `includes/class-biawin-crm-deficiency-workflow.php`
- `includes/class-biawin-crm-finance-review.php`
- `includes/class-biawin-crm-frontend.php`
- `includes/class-biawin-crm-import-service.php`
- `includes/class-biawin-crm-invoice-phone-service.php`
- `includes/class-biawin-crm-ownership-audit.php`
- `includes/class-biawin-crm-ownership-authorization.php`
- `includes/class-biawin-crm-ownership-lock-service.php`
- `includes/class-biawin-crm-ownership-otp.php`
- `includes/class-biawin-crm-ownership-repository.php`
- `includes/class-biawin-crm-ownership-risk-service.php`
- `includes/class-biawin-crm-ownership-schema.php`
- `includes/class-biawin-crm-ownership-transfer-service.php`
- `includes/class-biawin-crm-ownership-workflow.php`
- `includes/class-biawin-crm-ownership-write-guard.php`
- `includes/class-biawin-crm-receipt-staging.php`
- `includes/class-biawin-crm-refund-register.php`
- `includes/class-biawin-crm-rest.php`
- `includes/class-biawin-crm-workflow-service.php`
- `readme.txt`
- `tests/ownership-c2-hardening.php`
- `tests/ownership-c2-static.py`
- `tests/ownership-c2.php`

## 15. Git snapshot (uncommitted)

`git diff --stat` (tracked files only; new untracked classes/tests/report are listed above):

```text
 CHANGELOG.md                                       |   8 ++
 STRUCTURE.md                                       |   6 +
 admin/class-biawin-crm-admin.php                   | 124 ++++++++++-----------
 biawin-crm-integration.php                         |  15 ++-
 docs/DEFICIENCY-FINANCE-STATE-MACHINE.md           |   6 +
 includes/class-biawin-crm-activator.php            |  13 ++-
 includes/class-biawin-crm-after-sales-config.php   |   2 +-
 ...ss-biawin-crm-blacklist-final-commit-engine.php |  28 ++---
 .../class-biawin-crm-controlled-reconstruction.php |  22 ++--
 includes/class-biawin-crm-customer-service.php     | 119 +++++++++++---------
 includes/class-biawin-crm-db.php                   |  21 ++++
 includes/class-biawin-crm-deficiency-workflow.php  |  71 ++++++------
 includes/class-biawin-crm-finance-review.php       |  22 ++--
 includes/class-biawin-crm-frontend.php             |   8 +-
 includes/class-biawin-crm-import-service.php       |  88 +++++++--------
 .../class-biawin-crm-ownership-authorization.php   |  12 ++
 includes/class-biawin-crm-ownership-schema.php     |  21 ++--
 includes/class-biawin-crm-receipt-staging.php      |  21 ++--
 includes/class-biawin-crm-refund-register.php      |  12 +-
 includes/class-biawin-crm-rest.php                 |   6 +-
 includes/class-biawin-crm-workflow-service.php     |  53 +++++----
 readme.txt                                         |   2 +-
 22 files changed, 392 insertions(+), 288 deletions(-)
```

`git status --short`:

```text
 M CHANGELOG.md
 M STRUCTURE.md
 M admin/class-biawin-crm-admin.php
 M biawin-crm-integration.php
 M docs/DEFICIENCY-FINANCE-STATE-MACHINE.md
 M includes/class-biawin-crm-activator.php
 M includes/class-biawin-crm-after-sales-config.php
 M includes/class-biawin-crm-blacklist-final-commit-engine.php
 M includes/class-biawin-crm-controlled-reconstruction.php
 M includes/class-biawin-crm-customer-service.php
 M includes/class-biawin-crm-db.php
 M includes/class-biawin-crm-deficiency-workflow.php
 M includes/class-biawin-crm-finance-review.php
 M includes/class-biawin-crm-frontend.php
 M includes/class-biawin-crm-import-service.php
 M includes/class-biawin-crm-ownership-authorization.php
 M includes/class-biawin-crm-ownership-schema.php
 M includes/class-biawin-crm-receipt-staging.php
 M includes/class-biawin-crm-refund-register.php
 M includes/class-biawin-crm-rest.php
 M includes/class-biawin-crm-workflow-service.php
 M readme.txt
?? docs/OWNERSHIP-RECONCILIATION-C2-REPORT.md
?? includes/class-biawin-crm-customer-hold-service.php
?? includes/class-biawin-crm-invoice-phone-service.php
?? includes/class-biawin-crm-ownership-audit.php
?? includes/class-biawin-crm-ownership-lock-service.php
?? includes/class-biawin-crm-ownership-otp.php
?? includes/class-biawin-crm-ownership-repository.php
?? includes/class-biawin-crm-ownership-risk-service.php
?? includes/class-biawin-crm-ownership-transfer-service.php
?? includes/class-biawin-crm-ownership-workflow.php
?? includes/class-biawin-crm-ownership-write-guard.php
?? tests/ownership-c2-hardening.php
?? tests/ownership-c2-static.py
?? tests/ownership-c2.php
```

## 16. Upstream references checked

- [WordPress wpdb query and return-state behavior](https://developer.wordpress.org/reference/classes/wpdb/query/)
- [MySQL named-lock semantics](https://dev.mysql.com/doc/refman/8.4/en/locking-functions.html)
- [MySQL transaction characteristics and nesting restriction](https://dev.mysql.com/doc/refman/8.0/en/set-transaction.html)
- [WordPress dbDelta](https://developer.wordpress.org/reference/functions/dbdelta/)

- [MySQL locking reads and latest available data](https://dev.mysql.com/doc/refman/8.4/en/innodb-locking-reads.html)
