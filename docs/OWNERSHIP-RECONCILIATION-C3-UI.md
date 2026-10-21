# Ownership Reconciliation C3 — UI/UX (تطبیق مالکیت)

## 1. Baseline and scope

- Work branch: `feature/ownership-c3-ui`, created from the approved C2 checkpoint `38779e8` (`feature/ownership-reconciliation`, not modified).
- Plugin version unchanged: **0.8.65.19**. No schema, migration, state-machine, risk, SoD, OTP, hold, lock, transfer, finalization, Deficiency, Finance, Import or Attach change.
- C3 adds a presentation layer and thin transport adapters only. The C2 backend stays authoritative: **claim ≠ transfer**; ownership changes only through `Biawin_CRM_Ownership_Transfer_Service::execute()` after an approved decision.

## 2. Architecture

| File | Role |
|---|---|
| `includes/class-biawin-crm-ownership-presenter.php` | Read-only view models and Persian labels. Every case read starts from `Workflow::get()` / `Workflow::list_cases()`; enrichment only adds display context for rows the backend already authorized. Action visibility hints (never authority). Backend error → Persian message mapping. |
| `includes/class-biawin-crm-ownership-ajax.php` | One `wp_ajax_bcrm_ownership` action. Each command `op` validates nonce/session/input shape and makes **exactly one** call to an approved C2 command. Read-only lookups. |
| `includes/class-biawin-crm-ownership-ui.php` | Server-rendered workspaces, portal/admin wiring, asset enqueue. |
| `ownership-ui/ownership.js` | Vanilla ES5 controller (no framework/CDN): filters, request wizard, asset picker, action forms, accessible confirm dialog, stale-version handling. |
| `ownership-ui/ownership.css` | RTL-first responsive styles, dark mode via the portal's existing `.is-dark` shell. |
| `biawin-crm-integration.php` | Registers the three classes and `Biawin_CRM_Ownership_UI::init()` (only change to an existing file). |

### Why the UI is attached through hooks

The approved C2 regression suite (`tests/ownership-c2-static.py`) freezes `admin/class-biawin-crm-admin.php`, `includes/class-biawin-crm-frontend.php` and every tracked file under `assets/`. To keep that suite green without weakening it:

- **Portal:** `bcrm_view=ownership` is served through the WordPress `pre_do_shortcode_tag` filter for `[biawin_crm_portal]`; the page is wrapped in the existing portal shell (`Biawin_CRM_Frontend::render_shell`, invoked via reflection, with a plain fallback). A sidebar link «تطبیق مالکیت» is added through `do_shortcode_tag` only for users with an ownership workspace. All other portal views are untouched.
- **WP admin:** submenu `biawin-crm-ownership` under the existing `biawin-crm` menu.
- **Assets** live in `ownership-ui/`, not `assets/`.

### URLs

- Portal: `?bcrm_view=ownership&own_tab=request|mine|queue|supervisor|history`, case: `&own_case=<id>`.
- Pre-filled claimant: `&own_tab=request&claimant=<customer id>` (only applied if the user can access that profile).
- Admin: `admin.php?page=biawin-crm-ownership` with the same `own_tab` / `own_case` parameters.

## 3. Workspaces

| Workspace | Visible with | Content |
|---|---|---|
| درخواست تطبیق مالکیت | `submit_ownership_claim` | 5-step wizard: source (found via invoice code/phone; owner = source), claimant (accessible profiles only, must differ), explicit asset selection with per-invoice phone resolution, reason/notes, review → «ذخیره پیش‌نویس» or «ثبت درخواست تطبیق و ارسال برای بررسی». |
| پرونده‌های من | `view_own_ownership_claims` or `view_ownership_cases` | Claims requested by the user; filters active/draft/evidence/customer confirmation/decided/closed/cancelled. |
| صف کارشناس تطبیق مالکیت | `view_ownership_cases` | Backend list scope (all for supervisors, assigned for specialists); one filter per status, «همه فعال», «نهایی‌سازی ناتمام». Row: code, source, claimant, asset/invoice/phone counts, status, risk + reasons, assignee, age, hold/conflict/amendment/OTP indicators, supervisor state. |
| صف سرپرست / High Risk | `approve_high_risk_ownership_transfer` | `AWAITING_SUPERVISOR_APPROVAL` only; deciding specialist and SoD indicator (own decisions marked view-only). |
| بسته / لغوشده | as «پرونده‌های من» | Read-only history. |
| جزئیات پرونده | `Workflow::get()` authorization | Header, banners, profile comparison, assets (requested vs final plan, before/after), risk, holds/locks, evidence, OTP, decision summary, execution, immutable timeline, sticky action panel. |

## 4. Thin adapters added (`bcrm_ownership` ops)

All ops: logged-in, not `bcrm_disabled`, nonce `bcrm_ownership_ui`; actor, owners, capability, scope, state, SoD and `lock_version` are enforced by the backend command. After a command (success **or** failure) the adapter returns the fresh authoritative `version/status/execution` via `Workflow::get()`.

| op | Delegates to |
|---|---|
| `create_draft`, `update_draft`, `add_draft_asset`, `remove_draft_asset`, `submit`, `cancel` | `Biawin_CRM_Ownership_Workflow::*` |
| `add_evidence` | `Workflow::get()` (read authorization) → WordPress `media_handle_upload` with the backend's MIME allow-list → `Workflow::add_evidence()`; the unregistered upload is deleted if registration fails (same pattern as the existing financial-evidence upload). |
| `assign`, `start_review`, `request_evidence`, `record_contact`, `amend`, `set_final_plan`, `evaluate_risk`, `decide`, `supervisor_decision` | `Biawin_CRM_Ownership_Workflow::*` |
| `otp_issue`, `otp_verify` | `Biawin_CRM_Ownership_OTP::issue/verify` |
| `execute`, `resume` | `Biawin_CRM_Ownership_Transfer_Service::execute/resume` |
| `lookup` (read) | `Biawin_CRM_Customer_Service::search_related()` (existing portal exposure) + read-only lock/active-case/ambiguous-phone hints (`Invoice_Phone_Service::current()`, `Repository::conflicting()`). |
| `claimant_search` (read) | Customer search filtered by `Biawin_CRM_Frontend::can_access_customer()`. |
| `profile_summary` (read) | Name/code + active holds always; financials/phones only if `can_access_customer()` or the user reviews a case of that profile. |
| `normalize_phone` (read) | `Biawin_CRM_Normalizer::phone()`. |

No adapter writes a table, transitions state, evaluates risk or alters authorization. `tests/ownership-c3-static.py` enforces this.

## 5. Permissions

- Workspace visibility follows capabilities (never role names). Action visibility mirrors capability + state + case scope and is labelled as non-authoritative; every unavailable action is explained in the action panel.
- Requester: no specialist/supervisor controls; sees own evidence only and no risk reasons, snapshots, OTP, execution error or audit events (same split as `Workflow::get()`).
- Specialist: no supervisor approval control. Decision maker on a HIGH_RISK case: approval hidden with an SoD explanation; the backend still rejects a self-approval (tested).
- Administrator / portal admin keep their supervisor capability mapping.

## 6. State, risk, holds, locks, evidence, OTP, execution

- All ten backend statuses and four execution states have Persian labels and non-color-only badges. No frontend-only state exists.
- Risk is read from `risk_level`/`risk_reasons_json`; JavaScript never computes risk. Every backend reason is rendered.
- Customer Hold banner: «این پروفایل تا تعیین تکلیف پرونده تطبیق مالکیت فقط خواندنی است.» Persistent invoice lock: «تغییر مالکیت این فاکتور فقط از مسیر تطبیق مالکیت مجاز است.» Hold table (status, created, released, reason) and lock history (owner, reason, created, release, releasing case) are separate.
- Evidence: Persian kind labels, upload with type/note/conflicting flag (reviewers), preview (image/audio/file), filename, uploader, date, conflicting badge from the immutable `EVIDENCE_ADDED` event. Upload success is shown only after backend registration.
- OTP: only for explicit PHONE assets and reviewers; shows phone, purpose, sent/expiry, attempts, state. Never plaintext code or hash. Copy states OTP success does not transfer ownership.
- Execution: `execute` → «اجرای انتقال مالکیت» with a decision summary and confirmation. `FINALIZATION_FAILED` explains that ownership is already committed and offers only «ادامه نهایی‌سازی» (`resume`). There is no «Retry Transfer».

## 7. Accessibility and responsive behaviour

RTL/Persian first; semantic headings, labelled fields, fieldsets/legends for radio groups, `aria-current` for tabs/steps, `aria-live` regions, `role=alert` errors tied to forms/fields (`aria-invalid`, `aria-describedby`), focus-visible outlines, keyboard-operable controls, native `<dialog>` confirm with focus return (fallback `window.confirm`). Desktop dense tables collapse into labelled cards under 760px; case action panel is sticky on desktop and moves above content under 1100px. No horizontal scroll at 390px (checked with headless Chromium on rendered fixture pages). Reduced motion honoured.

## 8. Verification executed in Cloud

```text
php -n tests/ownership-c1.php                                     PASS 179
php -n -d extension=pdo -d extension=pdo_sqlite tests/ownership-c2.php      PASS 304 (includes c2-hardening)
php -n -d extension=pdo -d extension=pdo_sqlite tests/ownership-c3-ui.php   PASS 304 C2 + 275 C3
python3 tests/ownership-c2-static.py                              PASS (frozen files unchanged, lint 57 PHP files, diff --check)
python3 tests/ownership-c3-static.py                              PASS
node --check ownership-ui/ownership.js                            PASS
```

On Linux the Windows-specific `extension_dir=C:/php-8.4.4/ext` was replaced by the local extension directory, and `pdo` must be loaded before `pdo_sqlite`. PHP 8.3.6 was used; PHP 7.4 syntax compatibility was kept by hand but not executed.

Additionally (not committed, scratch only): fixture pages were rendered to HTML and driven in headless Chromium with a mocked AJAX endpoint — wizard happy path (create → 3 assets with version chaining → submit), explicit phone-mode validation, ambiguous-phone mode blocking, claimant≠source, inline required validation, stale-version banner; desktop/mobile screenshots without horizontal overflow.

## 9. Not verified here — required local acceptance

No WordPress, MySQL or real browser session against the plugin exists in Cloud. Before rollout, run in the local Runtime Lab (WordPress + MySQL + browser):

1. Portal `bcrm_view=ownership` inside the real shell (sidebar link, active state, dark mode, font switch) and the admin submenu.
2. Full flows with real roles: requester, specialist, supervisor, second supervisor, administrator; NORMAL and HIGH_RISK; supervisor rejection; withdrawal/rejection; cancellation; FINALIZATION_FAILED + resume (inject a recalculation failure).
3. Real media upload of every allowed type, preview rendering, rejected types, orphan cleanup.
4. OTP with a configured `biawin_crm_ownership_send_otp` adapter (delivery accepted/failed, throttle, expiry, attempts).
5. Hold effects visible in Customer profile / Deficiency / Finance / Attach (mutation blocked with the hold message) and unaffected modules otherwise.
6. Mobile/tablet devices, screen reader pass (NVDA/VoiceOver), keyboard-only pass.

## 10. Known gaps

- `Workflow::list_cases()` scopes users holding `view_ownership_cases` to *assigned* cases (supervisors: all). A specialist's own claims assigned to someone else therefore do not appear in «پرونده‌های من». Fixing it needs a backend scoped list method (e.g. `list_own_cases`), deliberately not added in C3.
- Lists page through `list_cases()` up to 1,000 cases per page view and filter client-side; very large queues need server-side status filtering in the backend list API.
- No entry button was added to the customer profile page (frozen file); use the claimant pre-fill URL above, or add a link in a later approved change to the profile view.
- Contact results are UI-provided values; only `current_owner_consent` has backend meaning (consent approval).
- Error texts map the known backend messages; unknown messages are shown verbatim with a Persian prefix.

## 11. C3.8 — legacy Attach ↔ Ownership bridge (runtime remediation)

**Root cause of the Local Runtime critical error.** `Biawin_CRM_Frontend::ajax_attach_invoice()` (`wp_ajax_bcrm_attach_invoice`) calls `Biawin_CRM_Customer_Service::attach_invoice()`. Its first write (invoice owner update) goes through `Biawin_CRM_DB::write()` → `Biawin_CRM_Ownership_Write_Guard`, which correctly throws `RuntimeException('Asset ownership is locked; use Ownership Reconciliation.')`. The frozen legacy handler has no `try/catch`, so the exception became a PHP fatal / WordPress critical error. The guard is correct and unchanged.

Also observed: for a **complete** source profile the guard first runs `Lock_Service::sync_completed(source)` and so materializes the persistent completion locks on the first protected write, then rejects it. This is unchanged C2 behaviour. It also explains why invoices of completed profiles are protected even before a lock row exists.

**Implementation (no frozen file touched).** `includes/class-biawin-crm-ownership-legacy-bridge.php` hooks the same legacy actions at priority **1**. The legacy callbacks stay at priority 10.

- `bcrm_attach_invoice`, `bcrm_attach_bundle`, admin-post `bcrm_front_attach_invoice` get a read-only pre-check:
  - The checks are: valid nonce, `bcrm_work_cases`, target access, invoice exists, owner ≠ target. The pre-check then looks for an active invoice lock, an active lock on an explicit bundle phone, and a hold on the target or source.
  - If prohibited, the bridge answers with `wp_send_json_error(..., 423)` (or a controlled `wp_die` page). The request ends, so the legacy handler never runs and nothing is written.
  - Otherwise it returns silently and the legacy handler runs exactly as before.
- All 15 covered legacy AJAX mutations and 11 admin-post mutations get a request-scoped `set_exception_handler`:
  - Any escaped Ownership lock/hold rejection becomes a controlled failure. For an Attach it is enriched with asset/source/claimant/CTA, which covers the completion-lock case.
  - An uncommitted legacy transaction is rolled back.
  - Any other exception remains a visible `unexpected` 500 error and is logged.

**Error contract** (`success:false`):

```json
{ "code": "ownership_locked | ownership_hold | ownership_busy | unexpected",
  "message": "🔒 تغییر مالکیت این فاکتور فقط از مسیر تطبیق مالکیت مجاز است. …",
  "detail": "(unexpected only) raw backend message",
  "ownership": { "asset_type": "INVOICE|PHONE", "asset_id": 8, "asset_label": "LAB-IMPORT-INV-001",
                 "locked": true, "held": [customer ids], "active_case": {"id": 0, "code": "OWN-…"} | null,
                 "source": {"id","name","code"}, "claimant": {"id","name","code"},
                 "request_url": "…?bcrm_view=ownership&own_tab=request&claimant=…&source=…&asset_type=INVOICE&asset_id=…" | null,
                 "action_label": "ثبت درخواست تطبیق مالکیت" | null } }
```

HTTP status: `ownership_locked`/`ownership_hold` 423, `active_ownership_case` 409, `ownership_busy` 503, `unexpected` 500. When the asset is already in an active Ownership case, no duplicate-claim CTA is offered and the case code is named. C3 adapter errors are now also classified as `ownership_locked`, `ownership_hold` and `active_ownership_case` (in addition to `permission`, `stale`, `state`, `input`, `not_found` and `domain`).

**Before mutation.** On the legacy profile page, `ownership.js` observes the frozen related-search results and calls the read-only op `legacy_status`, which requires access to the target profile.

- **Active lock:** «🔒 قفل مالکیت» + lock message + CTA, and the legacy Attach buttons are disabled with `aria-describedby`.
- **Hold:** the hold message.
- **Active case:** the case is named. This is reported separately from a persistent lock.
- **Complete source without a lock row yet:** only a «احتمال قفل مالکیت» hint and the CTA. The buttons stay enabled and the server decides.

If a lock or hold message still appears after an attempt, the CTA is appended to the legacy result message.

**Prefill contract.** `bcrm_view=ownership&own_tab=request&claimant=<id>&source=<id>&asset_type=INVOICE|PHONE&asset_id=<id>`. This extends the existing `claimant` hint. `Biawin_CRM_Ownership_Ajax::prefill()` re-validates everything on load:

- requires `submit_ownership_claim`;
- claimant must exist and pass `can_access_customer()`;
- the asset is re-read and its **current DB owner** becomes the source (a differing `source` hint is replaced, with a notice);
- the asset is dropped if missing, ownerless, or already the claimant's;
- source = claimant is dropped;
- the asset is re-read through `lookup()`, which re-reads the lock state;
- malformed values are ignored.

The wizard shows the source and claimant cards and highlights the suggested asset. It never selects the asset, never chooses the phone resolution, and never creates or submits anything. The user still explicitly chooses the asset, action, phone mode and reason, and confirms the submission.

**Formal workflow with a persistently locked invoice** (tested): `create_draft` → `add_draft_asset` (locked invoice) → `submit` all succeed and both holds are applied. A duplicate active claim is rejected (`active_ownership_case`). Execution moves the invoice and its lock to the claimant. A persistent lock never disables selection in the wizard; only an active conflicting case does.

**Customer search.** Locally verified PASS with `http://localhost:8088/?page_id=4&bcrm_view=customers&bcrm_filter=all&bcrm_per_page=50&bcrm_contact=all&bcrm_q=09120000007` (LAB-F-PENDING). The earlier failure was the URL without `page_id=4`. No customer-search source was changed.

**Local re-test still required:**
- real `admin-ajax.php` hook order with the actual frontend callbacks;
- browser Attach on `LAB-IMPORT-INV-001` (invoice 8): no critical error, Persian lock message, CTA, buttons disabled after search;
- CTA → prefilled wizard → draft → submit;
- a held profile's interaction/journey save shows the hold message;
- unlocked Attach on an incomplete profile is unchanged;
- admin-post Attach form;
- users without ownership capabilities (controlled message, no CTA).
