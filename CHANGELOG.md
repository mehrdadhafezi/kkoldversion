## 0.8.65.18 — Ownership Reconciliation C1

- Add seven ownership foundation tables and four nullable canonical invoice-phone columns.
- Add ownership specialist/supervisor capability definitions through the existing provisioning ledger.
- Add an opt-in, paginated read-only legacy invoice-phone audit; no backfill or ownership changes.
- Keep the upgrade from 0.8.65.17 scoped to C1, without replaying activation data repairs.
- No UI, workflow guards, transfer engine, or Deficiency/Finance changes.

## 0.8.65.17
- FIX — در «مرحله اقدام اولیه → ثبت تماس»، گزینه‌های «عدم پاسخ» و «در انتظار ارسال فیش» دوباره همیشه نمایش داده می‌شوند؛ اضافه‌شدن «ثبت پیگیری» دیگر این دو نتیجه تماس قدیمی را حذف نمی‌کند.
- «ثبت پیگیری» با پنج ریزوضعیت قبلی همچنان در همه پرونده‌های فعال رفع ناقصی باقی مانده است؛ این اصلاح فقط حذف ناخواسته گزینه‌های ثبت تماس را برمی‌گرداند.

## 0.8.65.15
- اصلاح سازگاری نصب‌های قدیمی برای «ثبت پیگیری برگشت از مالی»: marker مشترک قبلی روی بعضی نصب‌ها از قبل مقدار 6 داشت و باعث می‌شد sync جدید اجرا نشود. یک feature-marker مستقل و repair یک‌باره اضافه شد.
- capability `process_finance_return_followup` به‌صورت additive روی نقش‌های موجود مرتبط sync می‌شود؛ نقش حذف‌شده بازسازی نمی‌شود و explicit deny بازنویسی نمی‌شود.
- نمایش و ثبت backend دیگر فقط به capability تازه وابسته نیست؛ کارشناس رفع ناقصیِ تخصیص‌گرفته که `process_deficiency_case` دارد نیز به‌صورت backward-compatible «ثبت پیگیری» را می‌بیند و اجرا می‌کند.

## 0.8.65.14 — Returned-finance follow-up access for deficiency agents

- قابلیت «ثبت پیگیری» پرونده‌های برگشتی از مالی از حالت supervisor-only خارج شد و برای کارشناس رفع ناقصیِ دارای دسترسی عملیاتی نیز فعال است.
- capability جدید `process_finance_return_followup` به نقش‌های سرپرست و کارشناس رفع ناقصی اضافه شد؛ marker دسترسی به v6 افزایش یافت تا روی نصب‌های موجود نیز نقش‌ها sync شوند.
- backend ثبت پیگیری علاوه بر دسترسی‌های قبلی، capability جدید را می‌پذیرد و UI مرحله اقدام اولیه نیز همان مجوز را رعایت می‌کند.
- پنج ریزوضعیت قبلی بدون تغییر حفظ شدند؛ مسیر «ارسال به مالی و آپلود ادامه فیش‌ها» همچنان حداقل یک فیش جدید می‌خواهد.

## 0.8.65.13 — Returned-finance allocation + follow-up action UX

- تب «برگشتی از مالی» مثل سایر تب‌های عملیاتی رفع ناقصی، انتخاب چند پرونده و تخصیص/باز‌تخصیص مستقیم توسط سرپرست دارد؛ وضعیت `RETURNED_TO_DEFICIENCY` هنگام تخصیص حفظ می‌شود.
- backend تخصیص رفع ناقصی نیز `RETURNED_TO_DEFICIENCY` را می‌پذیرد تا تخصیص تب برگشت مالی فقط ظاهری نباشد.
- در پرونده برگشتی، «مرحله اقدام اولیه» گزینه «ثبت پیگیری» دارد و پنج ریزوضعیت مصوب زیر آن قرار گرفته‌اند: توافق مبلغ تائید شده، ادعای واریزی بیشتر، عدم پاسخ، درانتظار ارسال فیش مجدد، ارسال به مالی و آپلود ادامه فیش ها.
- در پرونده برگشتی، گزینه‌های تکراری «عدم پاسخ»، «در انتظار فیش» و «ادعای واریزی بیشتر» از شاخه‌های عمومی همان فرم حذف شده‌اند؛ برای برگشت مالی مرجع واحد آن‌ها «ثبت پیگیری» است.
- ارسال مجدد پرونده برگشتی به مالی از مسیر عمومی مسدود است و فقط از ریزوضعیت «ارسال به مالی و آپلود ادامه فیش ها» انجام می‌شود؛ الزام حداقل یک فیش جدید حفظ شده است.

## 0.8.65.12 — Deficiency per-tab assignment + finance-return follow-up + receipt supervisor controls

- سرپرست رفع ناقصی در تب‌های عملیاتی `new / assigned / review / contact / decision` می‌تواند پرونده‌های همان تب را انتخاب و مستقیم به یک یا چند کارشناس تخصیص/باز‌تخصیص دهد؛ وضعیت Workflow هنگام باز‌تخصیص حفظ می‌شود.
- پرونده‌های `RETURNED_TO_DEFICIENCY` از صف تخصیص حذف و در backend نیز غیرقابل تخصیص شدند؛ پرونده برگشتی با مالک قبلی رفع ناقصی ادامه پیدا می‌کند.
- برای پرونده برگشتی از مالی پنج نتیجه اختصاصی اضافه شد: توافق مبلغ تاییدشده، ادعای واریزی بیشتر، عدم پاسخ، در انتظار ارسال فیش مجدد، ارسال مجدد به مالی همراه با حداقل یک فیش جدید.
- سرپرست رفع ناقصی می‌تواند فیش بانکی پرونده فعال رفع ناقصی را حذف کند؛ review مرتبط نیز حذف و رخداد Audit/Timeline ثبت می‌شود.
- پروفایل مشتری دو کارت مالی جدید دارد: «مجموع تاییدها» و «مجموع عدم تاییدها». مجموع تاییدها از `approved_amount` و مجموع عدم تاییدها از مبلغ فیش‌های ردشده محاسبه می‌شود.

## 0.8.65.11
- گزارش فقط‌خواندنی «گزارش تأیید مالی» به پنل مدیریت اضافه شد؛ شامل خلاصه پروفایل‌ها، جزئیات فیش‌ها، فاکتورهای تأییدشده، عدم تأییدها و سایر وضعیت‌ها.
- کد فاکتورهای ثبت‌شده توسط تأیید مالی از `receipt_reviews.details_json` (`invoice_number` / `invoice_links`) در گزارش و خروجی Excel نمایش داده می‌شود.
- فیلتر تاریخ ارسال مالی، تاریخ بررسی، وضعیت فیش، نوع نتیجه، کارشناس مالی، بررسی‌کننده و جستجو بر اساس نام/کد مشتری/Case/کد فاکتور اضافه شد.
- خروجی XLSX پنج‌شیتی همان فیلترهای صفحه را رعایت می‌کند و گزارش هیچ تغییری در Workflow یا داده‌های CRM ایجاد نمی‌کند.

## 0.8.65.10
- رفع Warning تکرارشونده `Array to string conversion` در Timeline رفع ناقصی.
- متادیتای تو در توی بررسی مالی (چند فاکتور/چند ردیف عودت) اکنون بدون cast اشتباه Array و به‌صورت خوانا نمایش داده می‌شود.
- سازگاری Timeline با داده‌های جدید `invoice_links` و `refund_links` حفظ شد.

## 0.8.65.9
- Fixed assignment-page search context: searching a Customer/Case/phone/invoice while inside Finance or Deficiency assignment now stays on the `assign` tab, so a specifically searched case can be checked and assigned to a specifically selected agent.
- Finance case tables now show the currently assigned finance agent (or `تخصیص‌نیافته`) next to each customer, including filtered/search results.
- Refund `ثبت شده` receipt review now supports multiple invoice/phone/amount rows with `+`, persisted as `refund_links` in the existing receipt-review `details_json`; the approved/refund amount is the sum of those rows.

## 0.8.65.8
- رفع ناقصی: اختلاف جمع مبلغ فیش‌ها با مبلغ اعلامی دیگر مانع ارسال پرونده به تأیید مالی نیست؛ حالت کمتر، برابر و بیشتر همگی مجازند، مشروط به وجود حداقل یک فیش معتبر.
- اختلاف مبلغ (کمتر/بیشتر) برای Audit در لاگ ثبت می‌شود تا واحد مالی آن را بررسی کند، اما Workflow مسدود نمی‌شود.
- این قانون هم در مسیر staged upload و هم direct/legacy upload اعمال شد.

## 0.8.65.7
- رفع ناقصی: «اصلاح مبلغ اعلامی و تکمیل» به «اصلاح مبلغ اعلامی» تبدیل شد و فقط فیلد «مبلغ اعلامی اصلاح‌شده» + توضیحات دارد.
- مبلغ اصلاح‌شده همزمان در منبع canonical تعاملات، پروفایل مشتری و snapshot پرونده رفع ناقصی همگام می‌شود.
- تکمیل پروفایل فقط در برابری دقیق مبلغ اعلامی اصلاح‌شده با جمع زنده فاکتورها انجام می‌شود؛ در غیر این صورت پرونده در رفع ناقصی می‌ماند تا اقدام بعدی مثل بارگذاری فیش و ارسال به مالی انجام شود.
- وجود فیش قبلی دیگر مانع اصلاح مبلغ اعلامی نیست.

## 0.8.65.6
- Receipt viewer zoom hardening: transformed image bounds now remain inside the scrollable canvas, so the top/header of a zoomed receipt can always be reached instead of being clipped above the scroll origin.
- Zoomed receipt stage aligns content from the top while preserving the v0.8.65.5 pan/pinch/scroll viewer behavior.

## 0.8.65.5
- Finance completion no longer introduces a separate final-approval state: after every `bank_receipt` has a final receipt review, the modal enables one explicit `ثبت نتیجه و برگشت به رفع ناقصی` action.
- Finalization is transactional and re-checks canonical receipt/review counts on the server under a row lock before routing the case.
- Successful finalization writes `status=RETURNED_TO_DEFICIENCY`, `current_department=deficiency`, `finance_finished_at=NOW()` and records the canonical `FINANCE_RETURNED_TO_DEFICIENCY` audit action.
- Repeated/concurrent finalize requests are idempotent after a successful return; stale browser counts cannot finalize an incomplete case.

## 0.8.65.4
- Finance dashboard/queues now derive work state from real `bank_receipt` review progress instead of relying only on case status.
- Added separate waiting, assigned, partial-review, fully-reviewed, returned/history, and anomalous-state views plus receipt progress in tables/modal.
- One approved receipt can store multiple invoice-number + customer-phone associations while retaining legacy single-pair compatibility.
- Rebuilt receipt image viewer zoom/pan: +/- , 1:1, Fit, reset, rotate, Ctrl+wheel, pinch, drag/pan, bounded zoom and conditional scrollbars.

## 0.8.65.2
- اتصال فاکتور به دو اکشن صریح «فقط فاکتور» و «فاکتور + شماره انتخاب‌شده» تفکیک شد و fallback جابه‌جایی خودکار شماره حذف شد.
- نتیجه مالی پرونده کاملاً receipt-driven شد؛ پس از تعیین تکلیف همه فیش‌ها، پرونده به رفع ناقصی برمی‌گردد و از workload فعال مالی حذف می‌شود.
- فیلدهای تاریخ و مبلغ فاکتور از «تأیید با فاکتور» حذف شد؛ round-trip تاریخ پرداخت در UI شمسی اصلاح شد.
- شماره تماس در ثبت کارشناس رضایت‌سازی اجباری و مسیر/خدمات تا ثبت مبلغ اعلامی قفل شد.
- آپلود فیش برای کارشناس رفع ناقصی فعال شد؛ ارسال بدون فیش و ارسال با جمع فیش بیشتر از مبلغ اعلامی در backend مسدود شد.
- retry تکراری درخواست خدمات idempotent شد تا ثبت موفق قبلی به کارشناس به شکل خطای کاذب نمایش داده نشود.
- وضعیت INCOMPLETE_NEW برای کارشناس تخصیص‌یافته به شکل امن به INCOMPLETE_ASSIGNED ارتقا می‌یابد؛ پرونده واقعاً تخصیص‌نیافته همچنان قابل ارسال به مالی نیست.

## 0.8.64.1 — 2026-10-01 — Phase 2.1: inquiry architecture hardening

- **Root cause (0.8.64.0):** the «واحدهای قابل استعلام» tab rendered the unit's own `is_active` (plus
  `display_order`), and `save_unit_inquiry_config()` wrote both. Unticking it deactivated the whole unit.
  `unit_responders()` also treated `members` as supervisor + agents, so "agents only" and "both" could not
  be expressed.
- **Unit active ≠ inquiry enabled:**
  - Inquiry enabled is the existing column `service_units.can_receive_inquiry`, reused (no duplicate column).
  - The inquiry save is scoped to `can_receive_inquiry`, `requires_portal_response` and `inquiry_responders`.
    It never writes `is_active`, name, key, parent, supervisor, members, `display_order` or `updated_at`.
  - The admin handler forwards only those three fields; any other posted key is ignored.
  - Server-side validation: the unit must exist; booleans must be 0/1; the mode must be
    `members` | `supervisor` | `both`.
- **Eligibility:** one rule, `unit_accepts_inquiries()` = unit active AND inquiry enabled. Inquiry creation
  uses it, and an inactive unit is refused («این واحد غیرفعال است») even with inquiry enabled.
- **Responders:** `members` = member agents; `supervisor` = `supervisor_user_id` plus supervisor member rows;
  `both` = the union.
  - One resolver: `get_inquiry_responders($unit_id)`, which replaces `unit_responders()`.
  - It returns only existing, non-disabled users and is read-only.
- **Admin UI:**
  - «وضعیت واحد» is shown read-only.
  - «تنظیمات استعلام» has «استعلام‌پذیر» and «پاسخ از طریق پرتال لازم است» checkboxes (explicit hidden 0)
    and a single-select radio group for پاسخ‌دهندگان.
  - The row status shows مستثنی / غیرفعال (واحد) / پرتالی + دستی / فقط دستی.
- **Data:** no schema change, no migration. Valid stored values are preserved. A NULL or invalid stored
  responder value is read as `members` without being rewritten, and is normalized only when an admin saves
  that unit.

## 0.8.64.0 — 2026-10-01 — After-Sales Phase 2: admin configuration foundation

- **Catalogs (data-driven, nothing hard-coded for the future complaints UI):**
  - New generic table `catalog_items`: `catalog`, stable `item_key`, `label`, `description`, `is_active`,
    `sort_order`, `is_system`, `meta_json`, audit fields; unique per (`catalog`, `item_key`).
  - Holds `complaint_subject` and `complaint_outcome`.
  - Items are never hard-deleted by the UI. Deactivation only hides them from new selections.
  - Keys are immutable after creation.
  - `meta_json` reserves per-outcome actions (refund workflow, treasury request, financial inquiry, tag,
    required fields). The structure is in place; the actions are not implemented yet.
- **Existing data reused, not reinterpreted:**
  - `complaints.subject` (free text) stays as is. New nullable `complaints.subject_id` is NULL for every
    historical row.
  - New complaints store `subject_id` plus a label snapshot in `subject`, so old records keep their original
    subject after a rename or deactivation.
  - `complaints.resolution_outcome` keeps its keys. The three keys the current resolve flow already executes
    (`satisfied`, `service_request`, `refund`) are registered once as system outcomes (insert-only, ledger).
  - No subject list is seeded.
- **Existing forms now read the catalogs:**
  - The `?bcrm_customer=` complaint form shows a subject dropdown of active subjects. It falls back to free
    text only while none are configured, and free text is refused once subjects exist.
  - The resolve form takes labels, order and active state from the outcome catalog, limited to outcomes
    that have a handler. A deactivated outcome is refused server-side.
- **Inquiry providers** = the existing `service_units` (`can_receive_inquiry`, `requires_portal_response`,
  `display_order`, `is_active`) plus new `inquiry_responders` (`members` | `supervisor`). The users are the
  existing unit members. There is no duplicate "inquiry units" system.
- **Inquiries data model:**
  - New table `complaint_inquiries` holds N rows per complaint: `unit_id`, `unit_name_snapshot`,
    `mode` = `portal` | `manual`, `status`, request note, result key/text, notes, requested/responded
    by/at, `contacted_at`.
  - Data primitives: `request_portal_inquiry()` and `record_manual_inquiry()`. No workflow UI yet.
- **Admin:** new page «تنظیمات نارضایتی» with three tabs: موضوعات نارضایتی · نتایج / تصمیمات نارضایتی ·
  واحدهای قابل استعلام.
  - Each tab has search, add, edit, activate/deactivate, up/down ordering and status pills.
  - The units tab saves only inquiry fields; name, parent, supervisor and members are untouched.
- **Capabilities:**
  - Reuses the Phase 1 caps `manage_dissatisfaction_subjects` / `manage_dissatisfaction_outcomes`.
  - New `manage_inquiry_units` goes only to administrator and portal admin, granted additively through
    the ledger (`CAPS_MARKER_VERSION` 5).
  - No agent role gains anything.
- **Migration** `0.8.64.0 → migrate_0864_0`: idempotent, non-destructive and retryable; strict schema
  checks throw, so the version is not advanced and the next request retries.

## 0.8.63.2 — 2026-10-01 — Phase 1 final hardening (production disk/export fixes)

- **PROD FATAL:** `Call to undefined function disk_free_space()` in `handle_export_customers_excel()`. The
  host lists it in `disable_functions`; in PHP 8 a disabled function is undefined and `@` does not stop the
  fatal. **Fix:** new `Biawin_CRM_File_Safety` — `disk_free_bytes()` checks `function_exists` + `is_callable` +
  `disable_functions`, catches `Throwable`, returns NULL ("unknown") and the export continues. It is the only
  place in the plugin that calls `disk_free_space`/`disk_total_space`. Filter `bcrm_disk_free_bytes`.
- **PROD ENOSPC:** `fwrite(): errno=28 No space left on device`. Every write in the export paths is checked
  (partial writes included); `ZipArchive::close()` is checked (that is where ENOSPC surfaces).
  - Customer XLSX export → CSV fallback (streamed, no temp file) on low disk / temp unavailable / write failure.
  - Refund XLSX export → CSV fallback.
  - Workflow XLSX export → clean Persian error (HTTP 503) before any header is sent.
  - Import: a failed normalized-CSV write aborts with "disk full, nothing imported" instead of importing a
    silently truncated file.
  - Downloads run with `display_errors=0`, so notices never leak into files.
- **Temp files:** `bcrm-tmp-<purpose>-…` dirs under the WordPress temp dir (`get_temp_dir()`, honors
  `WP_TEMP_DIR`), never under uploads. They are removed on success, on failure and on shutdown; stale ones
  (>2h) are swept. Import staging (`uploads/biawin-crm-temp` >6h, finished `uploads/biawin-crm-imports`
  >48h) is swept as well. `tempnam()` is no longer used (a `false` return caused a `ZipArchive` TypeError).
- **Backups:**
  - Retention: keep the newest 3 (`BCRM_BACKUP_KEEP`, 1–20). Pruning runs before writing.
  - Location: `BCRM_BACKUP_DIR` can place backups outside uploads (recommended: a backup disk).
  - The directory is web-denied via `.htaccess` (2.2/2.4), `web.config`, and `index.php/html`.
  - Size estimated from `information_schema`; refuses to start when free space is known to be insufficient.
  - On any write failure the partial file is removed. Every outcome is audit-logged.
- **Migration lock:** atomic MySQL `GET_LOCK` (scoped to database + prefix, released in `finally` and
  automatically if PHP dies); the option lock is kept only as a fallback. Under the lock the version is
  re-read. A failure in any migration or in the schema step is logged (`upgrade_migration_failed`), the
  version is not advanced, and the next request retries.
- Phase 1 behavior unchanged: no user, password, role, capability, service or unit is created, reset or
  restored by an update.

## 0.8.63.0 — 2026-09-30 — After-Sales Phase 1 (foundation)

- **S4 (root cause):** `Activator::activate()` — executed on every version bump by `maybe_upgrade()`, by
  «ترمیم دیتابیس» and on activation — called `seed_default_operations_staff()` and
  `seed_default_specialist_staff()`, which `wp_create_user(…, fixed password)` for 27 staff + 8 specialist
  accounts + one agent per service unit, recreated deleted accounts, `set_role()` back to the default,
  rewrote display names and forced `bcrm_disabled=0`; `seed_default_services()` also created one supervisor
  login per default service. Separately, `ensure_roles_and_caps()` ran on EVERY request (plugins_loaded +
  admin_init) and re-`add_cap`ed every defined capability, silently undoing any capability an admin removed.
  **Fix:** all user seeding removed (no user is created/modified by the plugin outside explicit admin actions);
  roles/caps provisioned through a ledger (`biawin_crm_caps_ledger`): a role is created only if it never
  existed, a capability is granted only if the plugin never granted it before and the role does not carry
  it (granted or denied); nothing is ever removed; sync runs only when `CAPS_MARKER_VERSION` changes (now 4).
- **L2 (root cause):** `seed_default_services()` UPDATEd name/credit_cost/unit_key/is_active/sort_order of the 8
  default services on every activate, force-deactivated legacy codes, and reset each default unit's
  name/supervisor/active flag. **Fix:** insert-only by stable `service_code`/`unit_key`, with seed ledgers so a
  default an admin deleted is not re-inserted; no UPDATE/DELETE/TRUNCATE of catalog or units.
- **Units:** `service_units` + `parent_unit_id`, `unit_type`, `can_receive_inquiry`, `requires_portal_response`,
  `display_order` (+ indexes `parent_unit`, `type_active_order`; members index `user_role`). Initial units
  (`after_sales` ← `dissatisfaction`, `services`, `finance_approval`, `loan`, `treasury`) inserted once, never
  overwritten; existing service cartables typed `service` and parented under `services` one time only.
- **Roles:** new `bcrm_after_sales_manager`, `bcrm_loan_supervisor/agent`, `bcrm_treasury_supervisor/agent`;
  reused: `bcrm_complaint_supervisor/agent` (relabelled «نارضایتی‌ها» only if the label was still the default),
  `bcrm_service_*`, `bcrm_financial_*`. **Caps:** 21 after-sales capabilities (see QA report), granted
  additively; administrator receives all of them.
- **Admin UI (minimal):** «واحدها و اعضا» page — hierarchy, type, inquiry flags, order, active, edit link;
  handler validates parent cycles and accepts only CRM portal users as members; N+1 queries removed. The 21
  caps are grantable per user on «کاربران پرتال».
- **Migration:** `0.8.63.0 → migrate_0863_0` (idempotent, non-destructive, retryable; throws on schema failure).
- **Fixes:** fresh installs never created `refund_registers` (`AFTER` inside CREATE TABLE); default password
  removed from all shipped docs; staff-roster docs (`DEFAULT-*-USERS*.md`) no longer shipped in the ZIP.
- **Tests:** real WordPress 7.0.3 + MySQL 8.4 update simulation 39/39, unit handler 10/10, failure→retry PASS;
  new static suite 41/41; main harness 609/0; no regressions in other suites.

## 0.8.62.28 — 2026-09-29

- **NEW — پنجره بررسی فیش مالی (الگوی پنل مالی sales-network-v4 2.0.117):** در آن افزونه دکمه «مشاهده فیش»
  مودالی باز می‌کند که فیش و اطلاعات واریز کنار هم است و «تأیید این واریز / رد این واریز (با دلیل) / بازگشت
  به بررسی» همان‌جاست. همین الگو اینجا پیاده شد:
  - تریگرها `[data-bcrm-fin-review]`: دکمه بالای «فیش‌های این پرونده»، روی هر کارت فیش، و در ستون اقدام
    جدول‌های صف مالی (باز شدن مستقیم از فهرست، بدون ورود به صفحه پرونده).
  - نمایشگر: تصویر (بزرگ‌نمایی/کوچک‌نمایی/۱:۱/چرخش، کلیک = زوم)، PDF داخل صفحه، و برای HEIC/TIFF/Office/…
    کارت فایل با «باز کردن در تب جدید/دانلود»؛ نوار بندانگشتی همه فیش‌ها با رنگ وضعیت؛ قبلی/بعدی و کلیدهای
    ←/→ و Esc.
  - ستون کنار فیش: مشتری، Customer ID، موبایل، مدیر فروش، مبلغ اعلامی، مجموع فاکتورها، جمع فیش‌ها، جمع
    تأییدشده، باقیمانده، شمارنده بررسی‌نشده/تأیید/رد، توضیحات رفع ناقصی، سوابق پرونده.
  - اقدام روی همان فیش: «تأیید این فیش» با مبلغ قابل‌ویرایش (پیش‌فرض مبلغ فیش)، «رد این فیش» با دلیل اجباری،
    «بازگردانی به بررسی‌نشده»؛ پس از هر اقدام فیش بررسی‌نشده بعدی خودکار باز می‌شود و نشان روی کارت صفحه
    بدون رفرش به‌روز می‌شود.
  - «ثبت نتیجه نهایی مالی پرونده» داخل همان پنجره (با/بدون فاکتور/عدم تأیید + تقویم شمسی)؛ مبلغ پیشنهادی =
    جمع فیش‌های تأییدشده؛ از همان `process_action('finance_submit')` فرم معمولی عبور می‌کند.
- سرور: کلاس `Biawin_CRM_Finance_Review` + جدول `bcrm_receipt_reviews` (ساخت خودکار)؛ AJAX
  `bcrm_fin_case_review` / `bcrm_fin_receipt_review` / `bcrm_fin_case_decide` با nonce `bcrm_fin_review`،
  `can_access_case` و capabilityهای مالی؛ بررسی فقط در وضعیت‌های صف مالی؛ فیش باید متعلق به همان پرونده باشد؛
  رویدادهای Timeline: `RECEIPT_APPROVED` / `RECEIPT_REJECTED` / `RECEIPT_REVIEW_RESET`؛ خلاصه بررسی فیش‌ها
  (`receipt_reviews`) در `finance_result_json`.
- تست: TEST 31 (۲۴ ادعا)؛ جمع سوییت اصلی **609/0**؛ ادعاهای pin نسخه چهار سوییت به 0.8.62.28 به‌روز شد.
  تست مرورگر با سرور mock: تأیید → پرش خودکار به بعدی، رد بدون دلیل → خطا، PDF داخل صفحه، HEIC → کارت دانلود،
  نتیجه نهایی با مبلغ پیشنهادی 1,600,000 = جمع تأییدشده‌ها، حالت فقط‌خواندنی پس از برگشت، نمای موبایل.

## 0.8.62.27 — 2026-09-29

- **FIX — ریشه واقعی «حتی یک فیش هم ثبت نمی‌شود» (گزارش پنل: ۳۰ فیش در صف، ثبت نهایی ۳۱ شناسه →
  «شناسه ناشناخته» → rollback کل دسته):** mutex نسخه .26 روی `add_option` اتمیک نبود —
  `add_option()` وردپرس اول `get_option` می‌خواند و بعد `INSERT … ON DUPLICATE KEY UPDATE` می‌زند؛
  دو پردازش موازی هر دو «قفل» را می‌گرفتند و read→modify→write روی option مشترک همچنان ردیف‌های
  هم را پاک می‌کرد. به‌علاوه صف بین همه کاربران/پرونده‌ها مشترک بود و `consume()` کل صف را خالی
  می‌کرد (ثبت یک همکار آپلودهای در جریان بقیه را پاک می‌کرد).
- **معماری جدید صف:** جدول `{prefix}bcrm_receipt_stage` (ساخت خودکار در اولین استفاده با
  `CREATE TABLE IF NOT EXISTS`، مستقل از activation hook)؛ هر فیش = یک ردیف با INSERT اتمیک، بدون
  قفل؛ کلید یکتای `(user_id, case_id, content_hash)` برای dedupe موازی؛ فایل‌ها در پوشه خصوصی
  `uploads/bcrm-receipt-staging/` (با `.htaccess` deny) با نام `.bin`؛ TTL ۲۴ ساعت؛ `consume($ids)`
  فقط شناسه‌های ثبت‌شده را حذف می‌کند؛ صف option قدیمی و قفلش با اولین استفاده بازنشسته می‌شوند.
- **فرمت آزاد + حجم:** هر فرمتی پذیرفته می‌شود (`safe_filename`: پسوندهای اجرایی مثل php/html/svg
  با `.txt` خنثی می‌شوند؛ فایل بی‌پسوند موبایل از MIME نام‌گذاری می‌شود؛ `detect_mime` با finfo).
  import رسانه با `wp_handle_sideload(test_type=false)` + `wp_insert_attachment` (چون
  `media_handle_sideload` overrides را ثابت می‌گذارد و allowlist نقش کارشناس HEIC/Office/ZIP را رد
  می‌کرد). سقف هر فایل ۸ → ۵۰ مگابایت (و کمینه با upload_max_filesize/post_max_size).
- **ثبت نهایی سبک و مقاوم:** thumbnail/sub-size و big-image scaling هنگام finalize خاموش؛
  `ignore_user_abort`؛ پیش‌بررسی وجود همه فایل‌ها قبل از هر import (بدون rollback رسانه)؛ پیام
  دقیق «n فیش از N پیدا نشد» + `missing_ids`.
- **مرورگر (portal-fixes.js، تنها پیاده‌سازی — نسخه تکراری frontend.js حذف شد):** آپلود فوری پس از
  انتخاب (۳ موازی) با درصد پیشرفت و وضعیت هر ردیف؛ انتخاب چندفایلی → یک ردیف برای هر فایل؛ تلاش
  خودکار تا ۵ بار با backoff در قطعی/۵xx، تازه‌سازی nonce در ۴۰۱/۴۰۳؛ دکمه «تلاش دوباره»؛
  کم‌حجم‌سازی عکس‌های JPEG/PNG/WebP بالای ۲٫۵MB به JPEG ۲۵۶۰px؛ بازیابی فیش‌های آپلودشده همان
  پرونده پس از رفرش/خطا (`bcrm_stage_list`) با مبلغ‌های ذخیره‌شده؛ اعتبارسنجی مبلغ/فایل هر ردیف؛
  بررسی وجود همه شناسه‌ها روی سرور پیش از ثبت و آپلود خودکار دوباره فیش گم‌شده؛ فرم نهایی
  فایل‌ها را **دوباره نمی‌فرستد** (قبلاً کل فایل‌ها در POST نهایی هم می‌رفتند و به post_max_size
  می‌خوردند)؛ لغو `confirm()` دیگر آپلود/ثبت را ادامه نمی‌دهد و پنجره تأیید دوبار باز نمی‌شود.
- **UI:** چک‌باکس‌های «ارسال فیش/وضعیت عودت» که به نوار تمام‌عرض تبدیل شده بودند اصلاح شدند؛
  استایل وضعیت ردیف (روشن/تیره، موبایل)؛ پنل تشخیص: سلامت جدول صف و پوشه موقت، فیش‌های منتظر
  «شما برای این پرونده»، حداکثر حجم مؤثر، لیبل رویدادهای جدید.
- تست: TEST 30 بازنویسی شد (سناریوی ۳۰ فایل + ۳۱ شناسه، ایزوله‌بودن کاربر/پرونده، مالکیت شناسه،
  dedupe، فرمت آزاد + خنثی‌سازی، فایل خالی/INI_SIZE، قراردادهای JS)؛ جمع سوییت: **585/0**.
  تست مرورگر واقعی با سرور mock: ۱۳ فایل چندفرمتی با ۳ خطای ۵۰۳ تزریقی، حذف ردیف، فایل گم‌شده روی
  سرور (آپلود خودکار دوباره)، POST نهایی بدون فایل (~۵KB)، بازیابی پس از رفرش، عکس ۲۰٫۶MB → ۰٫۶۵MB.

## 0.8.62.26 — 2026-09-29

- **FIX — ریشه‌یابی نهایی «حتی یک فیش هم ثبت نمی‌شود» از دل گزارش پنل تشخیص:** رویدادهای
  «ثبت نهایی دسته فیش‌ها ناموفق + تعداد شناسه: 3/6» و «شناسه فیش ناشناخته در ثبت نهایی» با
  چیپ «فیش‌های در صف: 7» یعنی آپلودها سالم به سرور رسیده‌اند ولی صف مشترک بین پردازش‌های موازی
  از دست می‌رفت: هر آپلود فیش یک درخواست HTTP جداگانه است و همه از یک option سایت read→write
  می‌کنند؛ بدون قفل، نوشتنِ یکی، فایلِ تازه‌ثبت‌شدهٔ دیگری را پاک می‌کرد → finalize همان شناسه را
  «ناشناخته» می‌دید و **کل دسته rollback می‌شد** (و صف نیم‌خالی برای پرونده بعدی هم مزاحم بود).
- فیکس: mutex فرایندی (`add_option` create-or-fail + انتظار ۵ ثانیه‌ای + آزادسازی تضمینی با
  `register_shutdown_function`) دور هر خواندن/نوشتن صف (stage/remove/finalize/reset)؛ finalize
  حالا صف را **تازه و زیر قفل** می‌خواند؛ `reset_pool` (مصرف موفق و بازسازی پس از fail کامل)
  هم زیر قفل؛ صف نیم‌خالیِ به‌جامانده از نسخه‌های قبل با اولین finalize موفق یا fail کامل پاک می‌شود.
- پنل تشخیص: لیبل‌های جدید (قفل صف شلوغ، شناسه ناشناخته + علت)، فیلدهای شناسه/دلیل/تعداد در صف،
  راهنمای صف مشترک با شماره پرونده، و «نامشخص» وقتی صف همین لحظه در حال نوشتن است.
- تست: **TEST 30** (قفل/timeout/ثابت‌ماندن صف پس از stage→remove→stage/finalize تازه زیر قفل)؛
  جمع سوییت: **569/0** + رگرسیون عودت 141/0.

## 0.8.62.25 — 2026-09-29

- **FIX — هات‌فیکس فتال صفحه پرونده:** روی هاست کاربر `disk_free_space` در لیست
  `disable_functions` بسته بود و در PHP 8 فراخوانی آن (حتی با `@`) Fatal Error می‌داد —
  رندر صفحه پرونده (هر دو نما) کامل می‌شکست. چیپ «فضای خالی دیسک» حالا با گارد
  `function_exists('disk_free_space')` ساخته می‌شود؛ در صورت غیرفعال بودن تابع، «نامشخص»
  نمایش داده می‌شود و بقیه پنل تشخیص سالم کار می‌کند.
- تست: ادعای رگرسیون به TEST 29 اضافه شد (گارد function_exists پین شد). جمع سوییت: **552/0**.

## 0.8.62.24 — 2026-09-29

- **NEW — پنل تشخیص آپلود فیش («حتی یک فیش هم ثبت نمی‌شود») روی صفحه پرونده:** گزارش کاربر این بود
  که بعد از «ارسال به تایید مالی» حتی یک فیش هم ثبت نمی‌شود؛ زنجیره آپلود (staging، آپلود موازی
  ۴تایی، finalize) دست‌نخورده و سالم است و هیچ رگرسیونی از 0.8.62.23 نیامده — چون علت روی سرور
  واقعی قابل حدس نبود، این نسخه مشکل را **قابل مشاهده** می‌کند: بخش «وضعیت آپلود فیش‌ها» در هر دو
  نمای رفع ناقصی و مالی نشان می‌دهد نشست ورود، توکن آپلود، فیش‌های در صف staging، سقف‌های PHP
  (max_file_uploads / upload_max_filesize / post_max_size)، فضای دیسک و ۱۰ رویداد آخر آپلود
  audit_log (با لیبل فارسی و فیلدهای زمینه مثل nonce/token/حجم).
- `analyze_case_receipts` (پارسر مبلغ فیش پنل .23) به متد مشترک عمومی تبدیل شد؛ هیچ رفتار جدیدی
  تغییر نکرد. CSS پنل با سیستم طراحی خود CRM (روشن + تیره) به portal-fixes.css اضافه شد.
- تست: **TEST 29** با ~۱۹ ادعا — رندر واقعی پنل در هر دو نما، صف خالی → «هیچ رویداد آپلودی ثبت
  نشده»، stage واقعی یک فیش → چیپ «1» + رویدادهای فارسی، پوشش رویدادهای stage_request_received /
  stage_auth_rejected / stage_no_payload. جمع سوییت: **551/0**.

## 0.8.62.23 — 2026-09-28

- **NEW — پنل تایید مالی به سبک پلاگین فروش‌نت (با استایل خود CRM):** صفحه پرونده Workflow حالا
  بخش «فیش‌های این پرونده» دارد — کارت تک‌تک فیش‌ها با **مبلغ خود فیش** (استخراج از یادداشت ردیف،
  ارقام فارسی پشتیبانی می‌شود)، لینک مشاهده فایل، نوع و زمان ثبت؛ بالای آن سه کارت جمع
  (مبلغ اعلامی مشتری / جمع فیش‌ها / باقیمانده تا مبلغ اعلامی).
- فرم «ثبت نهایی تایید مالی» خلاصه همان سه عدد را بالای فرم دارد؛ فیلد «مبلغ تاییدشده بدون
  فاکتور» به‌طور پیش‌فرض با **باقیمانده** پر می‌شود (فقط وقتی خالی است؛ تایپ کاربر بازنویسی نمی‌شود).
- پرونده بدون فیش: پنل فیش‌ها نمایش داده نمی‌شود (بدون بلاک خالی)؛ فرم مالی مثل قبل کار می‌کند.
- پارسر مبلغ فیش ارقام فارسی (`۸۰۰,۰۰۰`) و جداکننده‌های `,` و `،` را می‌فهمد؛ ردیف‌های بدون مبلغ
  با «نامشخص» نمایش داده می‌شوند و در جمع وارد نمی‌شوند.
- تست: **TEST 28** با ۲۳ ادعا — رندر واقعی صفحه پرونده (با ۲ فیش فارسی/لاتین، سرریز مبلغ،
  بی‌فیش) + ثبت تایید مالی با فاکتور از مسیر واقعی `finance_submit`. جمع سوییت: **532/0**.

## 0.8.62.22 — 2026-09-28

- **FIX — آپلود دسته‌های بزرگ فیش («فقط تعداد محدودی آپلود می‌شود»):** بازبینی کامل زنجیره آپلود؛
  معماری staging درست است ولی سه گلوگاه برای دسته‌های ۵۰–۱۰۰ تایی باقی بود:
  ① **آپلود سریالی** — هر فایل یک AJAX پشت‌سرهم ۱–۳ ثانیه؛ مجموع زمان به timeout سرور/proxy
  می‌خورد و کلیدهای در صف مانده دور ریخته می‌شدند → حالا **پنجره موازی ۴تایی**
  (`CONCURRENT=4` در portal-fixes.js و frontend.js؛ ترتیب ids = ترتیب DOM حفظ می‌شود؛ در اولین
  شکست صف فوراً قطع تا پیام دقیق خطا سریع برسد).
  ② **Finalize یک‌درخواستی** — کل دسته (media import + metadata + DB) در همان POST نهایی
  materialize می‌شود و `max_execution_time` هاست وسط کار آن را می‌کشت؛ چون kill ناگهانی از
  رولبک می‌گذرد فقط فیش‌های قبل از kill ثبت می‌ماندند (دقیقاً علامت گزارش‌شده) → `finalize_for_case`
  حالا `@set_time_limit(0)` + `wp_raise_memory_limit('image')` دارد؛ `ajax_stage` هم
  `@set_time_limit(0)`.
  ③ **سقف گارد تعداد** `MAX_STAGED_FILES` از ۲۰۰ به **۱۰۰۰** رسید (فقط DoS guard؛ هرگز سقف کسب‌وکار نبوده).
- تست‌ها: هارنس **TEST 24d** — دسته واقعی **۱۰۰ فیش** از مرحله‌گذاری تا نهایی‌سازی، همه هویت‌ها
  یک‌بار persist، مبلغ↔فایل سالم، پرونده → FINANCE_REVIEW_PENDING → **507/0**.

## 0.8.62.21 — 2026-09-28

- **NEW — همه تقویم‌های فرم‌های Workflow شمسی شدند:** سه ورودی `type="date"` میلادی باقی‌مانده
  (تاریخ پیگیری در «ثبت تماس»، تاریخ فاکتور در «تایید با فاکتور»، تاریخ پرداخت در «تایید بدون
  فاکتور») به دیت‌پیکر جلالی date-only (`data-bcrm-jalali-date`) تبدیل شدند — همان موتور تقویم
  v0.7.5 «پیگیری بعدی»، در `portal-fixes.js` (پرتال + ادمین) با استایل اضافه‌شده به `admin.css`.
- **تبدیل سمت سرور:** `Biawin_CRM_Normalizer::date()` — شمسی `1404/07/05` → میلادی `2025-09-27`
  (ارقام فارسی/عربی، جداکننده `/` یا `-`، بدون صفر پیش‌شماره)؛ ورودی میلادی دست‌نخورده رد می‌شود؛
  ورودی نامعتبر → رشته خالی (نه تاریخ جعلی). `jalali_to_gregorian` shared در Normalizer؛
  `record_contact()` و `record_finance_result()` هر سه فیلد را از این مسیر می‌خوانند.
- **نمایش شمسی:** Timeline و جزئیات پرونده (`format_timeline_meta`) تاریخ‌های میلادی ذخیره‌شده
  (`follow_up_date`/`invoice_date`/`payment_date`) را شمسی نمایش می‌دهند (`gregorian_to_jalali`
  محلی در کلاس Workflow).
- سازگاری: REST قبلاً شمسی می‌پذیرفت (`sanitize_datetime`) — بدون تغییر؛ داده‌های میلادی قدیمی
  خواندنی می‌مانند و فقط نمایششان شمسی می‌شود.
- تست‌ها: هارنس **TEST 24c** (runtime تبدیل ۱۴۰۴/۰۷/۰۵→۲۰۲۵-۰۹-۲۷، ثبت تماس با تاریخ شمسی که در
  DB میلادی می‌نشیند، رد تاریخ نامعتبر + ادعاهای استاتیک) → **493/0**؛ d593 (+۱ ادعا) → **32/0**؛
  `node --check assets/portal-fixes.js` PASS.

## 0.8.62.20 — 2026-09-27

- **NEW — نتیجه تماس «در انتظار ارسال فیش» (`awaiting_receipt`) در مرحله «ثبت تماس» Workflow:**
  گزینه جدید بعد از «پیگیری مجدد» در سلکت نتیجه تماس (`data-bcrm-step=contact`). رفتار مثل
  پیگیری مجدد: پرونده در `CUSTOMER_CONTACT_PENDING` می‌ماند (منتظر مشتری) و به «در حال بررسی»
  نمی‌رود. در metadata رویداد `contact_outcome=awaiting_receipt` ثبت و در خروجی اکسل (شیت
  «اقدام‌ها» و ستون «ریز وضعیت» شیت «پرونده‌ها») با برچسب «در انتظار ارسال فیش» نمایش داده می‌شود؛
  `last_contact_outcome` پرونده هم همان ذخیره می‌شود.
- هارنس: اسکیمای SQLite هم‌راستا با پروداکشن (`last_contact_outcome`/`follow_up_at`) + **TEST 24b**
  runtime واقعی: ثبت تماس با گزینه جدید، ماندن پرونده در CUSTOMER_CONTACT_PENDING، metadata و
  last_contact_outcome، رد گزینه ناشناس → **476/0**.

## 0.8.62.19 — 2026-09-27

- **NEW — ستون «مرحله اقدام» در خروجی اکسل Workflow (هر دو شیت):** دقیقاً همان گزینه‌های سلکت
  `workflow_step` فرم اقدام کارشناس: ثبت تماس / ثبت نتیجه بررسی / ارسال به تایید مالی /
  ثبت فاکتور برگشتی از مالی / اصلاح مبلغ اعلامی و تکمیل / تکمیل پروفایل / بایگانی؛ به‌علاوه
  تخصیص، بارگذاری مدرک، ثبت نتیجه مالی و تکمیل خودکار برای رویدادهای سیستمی. نگاشت از
  `action_type` رویداد (`$step_of`) — چون `workflow_step` خودش در metadata ذخیره نمی‌شود و
  `action_type` نگاشت یک‌به‌یک آن است. در شیت «پرونده‌ها» ستون جدید **بین «وضعیت» و «ریز وضعیت»**
  (مربوط به آخرین اقدام) و در شیت «اقدام‌ها» بعد از «رویداد» برای هر ردیف؛ کوئری last-action
  حالا `action_type` را هم می‌خواند.
- تست‌ها: هارنس (+۶ ادعای جدید، اصلاح ادعای هدر 62.18) → **467/0**؛ d593 (+۱ ادعا) → **31/0**؛
  smoke واقعی: ترتیب ستون‌ها و برچسب مراحل در هر دو شیت روی XLSX تولیدشده اعتبارسنجی شد.

## 0.8.62.18 — 2026-09-27

- **NEW — ستون‌های «وضعیت / ریز وضعیت / آخرین اقدام» در شیت «پرونده‌ها» خروجی اکسل Workflow:**
  ستون «وضعیت» (موجود) کنار ستون جدید **«ریز وضعیت»** = زیر وضعیت آخرین اقدام پرونده (نتیجه تماس /
  نتیجه بررسی / نتیجه مالی / نوع مدرک / وضعیت عودت — از `metadata_json` آخرین رویداد) +
  **«تاریخ آخرین اقدام»**، **«ساعت آخرین اقدام»** (جداشده از `created_at`) و
  **«آخرین اقدام‌کننده»** (display_name کاربر ثبت‌کننده آخرین رویداد).
  کوئری: `MAX(id) GROUP BY case_id` روی `case_actions` + نگاشت `last_map` (جدیدترین id برنده است)؛
  پرونده‌های بدون اقدام خالی می‌مانند. شیت «اقدام‌ها» حالا از همان closure برچسب‌گذاری ریز وضعیت
  (`sub_of_meta`) استفاده می‌کند — یک منبع حقیقت.
- تست‌ها: هارنس (+۸ ادعای جدید در TEST 27) → **461/0**؛ d593 (+۱ ادعا) → **30/0**؛ smoke واقعی:
  XLSX تولید و ستون‌های جدید روی دو پرونده (با/بدون اقدام) اعتبارسنجی شد.

## 0.8.62.17 — 2026-09-27

- **NEW — خروجی اکسل کامل گزارش Workflow (چندشیتی):** دکمه «خروجی اکسل کامل (پرونده‌ها + اقدام‌ها و
  ریز وضعیت‌ها + خلاصه)» در گزارش‌های رفع ناقصی و مالی کنار Export CSV. یک فایل XLSX با سه شیت:
  ① «پرونده‌ها» — همان ستون‌های CSV (با کارشناس رفع ناقصی/مالی و وضعیت عودت)؛
  ② «اقدام‌ها» — تک‌تک رویدادهای `case_actions` با برچسب فارسی رویداد، از/به وضعیت، کاربر، زمان،
  **زیر وضعیت هر مرحله** (نتیجه تماس / نتیجه بررسی / نتیجه مالی / نوع مدرک / وضعیت عودت از
  `metadata_json`) و ستون‌های داده هر مرحله: مبلغ ادعایی مشتری، اختلاف تاییدشده، مبلغ اعلامی
  اصلاح‌شده، مبلغ تایید نهایی، مبلغ و شماره فاکتور برگشتی، شماره تماس ثبت‌شده، تعداد و جمع فیش‌ها،
  تاریخ پیگیری و توضیحات (سقف ۲۰,۰۰۰ ردیف، ترتیب زمانی صعودی)؛
  ③ «خلاصه» — pivot شمارش هر (رویداد × زیر وضعیت) مرتب‌شده نزولی.
- مولّد `xlsx_write_multi()` بدون وابستگی خارجی (inline strings، ZipArchive، flush بافر خروجی مثل
  v0.8.62.14 برای سازگاری با Elementor). مسیر `admin_post_bcrm_deficiency_export_excel` با همان
  nonce و همان گیت گزارش‌ها (`view_deficiency_reports`/`view_finance_reports`/`bcrm_reports`).
- تست‌ها: هارنس TEST 27 (۲۲ ادعای جدید) → **453/0**؛ smoke-time تولید XLSX واقعی + اعتبارسنجی
  ZipArchive (نام شیت‌ها، pivot، ستون‌های مرحله‌ای)؛ d593 (+۳ ادعا) → **29/0**؛ پین‌ها → 0.8.62.17.

## 0.8.62.16 — 2026-09-27

- **NEW — تخصیص از فایل اکسل در صفحه «تخصیص شماره موبایل»** (`?bcrm_view=assignments` در پرتال و صفحه ادمین):
  کارت جدید بین «جستجو و تخصیص مستقیم» و «Rule مدیر فروش» — فایل XLSX/CSV شامل ستون `CustomerID` +
  دراپ‌داون تک‌کارشناس. سرویس `assign_profiles_from_customer_file()` با همان قواعد و قفل‌گذاری
  `handle_assignment()` (قفل ردیف مشتری + تخصیص‌های فعال با `FOR UPDATE`، اصلاح duplicate، reassign یا
  insert، تغییر status مشتری به assigned) و لاگ `assigned_to_agent` با `source=excel` + Activity
  `customer_assigned` + رویداد batch `assignment_excel_batch`.
  تطبیق مشتری: `source_customer_id` → شناسه داخلی → `customer_code`؛ dedupe شناسه‌های تکراری؛
  شناسه ناشناخته با شماره سطر اکسل در «جزئیات آخرین تخصیص» (همان transient/result UI موجود، + خط
  summary). هندلر `admin_post_bcrm_assign_customers_excel` با nonce مستقل و گیت `bcrm_manage_cases`.
- تست‌ها: هارنس TEST 26 (۲۵ ادعای جدید) → **431/0**؛ d593 (+۳ ادعا) → **26/0**؛ پین‌ها → 0.8.62.16.

## 0.8.62.15 — 2026-09-27

- **NEW — تخصیص دسته‌ای از فایل اکسل (تب «تخصیص»، رفع ناقصی + مالی):** سرپرست یک فایل XLSX/CSV
  شامل ستون `CustomerID` آپلود می‌کند و یک کارشناس مقصد انتخاب می‌کند؛ برای هر Customer ID همه
  پرونده‌های باز (به‌جز `COMPLETED`/`ARCHIVED`) به همان یک کارشناس تخصیص می‌یابد
  (`assign_from_customer_file()` → `assign_cases()` تراکنشی با `FOR UPDATE`).
  تطبیق مشتری: `source_customer_id` → شناسه داخلی → `customer_code`؛ شناسه‌های تکراری dedupe و
  گزارش؛ شناسه ناشناخته (`not_found`) و مشتری بدون پرونده باز (`no_case`) با شماره سطر اکسل در
  نتیجه.nonce مستقل `bcrm_deficiency_assign_upload` + همان دسترسی تخصیص + لاگ ممیزی per-customer و per-batch.
- تست‌ها: هارنس TEST 25 (۱۷ ادعای جدید)؛ پین‌های d593/d594/d598/d599 → 0.8.62.15.

## 0.8.62.14 — 2026-09-26

- **CRITICAL FIX — دانلود «خروجی اکسل» در پرتال (المنتور):** خروجی داخل شورت‌کد اجرا می‌شد وقتی
  قالب canvas المنتور هدرها را فرستاده بود → «Cannot modify header information» + چاپ باینری ZIP در صفحه.
  خروجی حالا روی `template_redirect` (اولویت ۱، قبل از هر خروجی) رهگیری می‌شود
  (`maybe_intercept_refund_export`)؛ شرط: view=refunded + bcrm_rf_export=1 + nonce همان
  `bcrm_rf_export` داخل خود خروجی. در نویسنده XLSX/CSV بافرهای خروجی قبلی پاک می‌شود.
- تست‌ها: 383/0 · 141/0 · پین‌ها → 0.8.62.14.

## 0.8.62.13 — 2026-09-26

- **NEW — فرمول مانده چند-فاکتوری:** عودت قطعی = جمع «آخرین رکورد هر کد فاکتور»
  (`GROUP BY invoice_code` روی MAX(id) هر گروه؛ آپلود مجدد همان کد = جایگزینی همان گروه).
  رکوردهای قدیمی بدون کد فاکتور یک گروه مشترک برای کل مشتری می‌سازند (سازگار با داده Customer-ID محور قبلی).
  اعمال‌شده در `refunded_amount_expressions()` (لیست/KPI/خروجی اکسل تب عودت‌ها) + `confirmed_total()` +
  `refund_amounts_for()` + `balance_for()` (کارت پروفایل).
- **NEW — ابزار حذف رکوردهای قبلی:** در هر دو صفحه «ثبت عودت‌ها» و «ثبت استفاده‌شده‌ها» —
  فیلتر نوع (عودت/استفاده‌شده)، فیلتر «فقط ردیف‌های بدون کد فاکتور»، فیلتر کد Batch،
  پاک‌سازی خودکار تگ «عودت قطعی» مشتری‌هایی که دیگر رکورد اکسلی ندارند (تگ اعلامی حفظ می‌شود)،
  رکوردهای اعلامی کارشناس هرگز حذف نمی‌شوند، همه‌چیز در audit log.
- رگرسیون SQLite: فیکسچر += invoice_code + دو کد فاکتور برای مشتری ۱ (SUM=1152) — **141/0**؛
  هارنس: **383/0** (۷ ادعای جدید فرمول/ابزار حذف).

## 0.8.62.12 — 2026-09-26

- **NEW — وضعیت تخصیص هر کارشناس:** در تب «تخصیص» جدولی جدید تعداد پرونده‌های هر کارشناس را به تفکیک
  وضعیت (تخصیص‌یافته، در حال بررسی، منتظر مشتری، ارسال به مالی، برگشتی از مالی، آماده تصمیم نهایی —
  و معادل‌های مالی) نشان می‌دهد؛ هر عدد کلیک‌پذیر است و همان فیلتر را در تب «برگشت تخصیص» باز می‌کند.
- **NEW — تب مستقل «برگشت تخصیص»:** فیلتر کارشناس (یا همه کارشناسان) + انتخاب چند وضعیت برگشت +
  صفحه‌بندی واقعی (10/20/50/100/200 در صفحه) + چک‌باکس «انتخاب همه در این صفحه»؛ برگشت با کلید
  «برگشت تخصیص انتخاب‌شده‌ها». بک‌اند حالت agent_id=0 (همه کارشناسان) را با حفظ تراکنش/قفل ردیف و
  رویداد ممیزی پشتیبانی می‌کند؛ فقط ردیف‌های همچنان در وضعیت تخصیص‌یافته برمی‌گردند.
- **NEW — صفحه‌بندی + انتخاب‌همه در همه جداول تخصیص/بررسی:** سقف ثابت 200 ردیف حذف شد؛
  COUNT + LIMIT/OFFSET با انتخاب «نمایش n در صفحه» و ناوبری قبلی/بعدی.
- هارنس‌ها: d593 به‌روز (تب برگشت، گزارش وضعیت، انتخاب‌همه، صفحه‌بندی)؛ پین‌های نسخه → 0.8.62.12.

## 0.8.62.11 — 2026-09-26

- **NEW — تطبیق اکسل عودت با کد فاکتور یا Customer ID:** هر ردیف اول با «کد فاکتور» (یونیک) تطبیق
  داده می‌شود؛ در نبود آن با «Customer ID». اگر هر دو ستون باشند کد فاکتور ملاک است؛ هیچ‌کدام
  به‌تنهایی اجباری نیست (حداقل یکی لازم است). نرمال‌سازی کد (ارقام فارسی/عربی، ي/ك، zero-width)،
  fallback صفرهای ابتدایی و جست‌وجوی جزئی. ستون جدید `invoice_code` در `refund_registers`
  (نصب جدید + ارتقای امن idempotent؛ بدون تغییر داده).
- **NEW — رکورد «استفاده شده» (source_type='used'):** آپلود جداگانه در منوی جدید «ثبت استفاده‌شده‌ها»
  (نونس مستقل `bcrm_refund_used_upload`)، فهرست اخیر مجزا، بدون هیچ تگی؛ ستون وضعیت اختیاری
  (خالی = معتبر). آپلود مجدد همان کلید = جایگزینی (Last-Record، هرگز SUM).
- **NEW — فرمول مانده همه‌جا:** مجموع فاکتورها − آخرین عودت قطعی اکسل − آخرین «استفاده شده»
  (کارت مانده پروفایل + باکس جدید «استفاده شده»، ستون مانده تب عودت‌ها، KPI جمع مانده/منفی،
  فیلترهای منفی/مثبت/صفر، خروجی اکسل). یک عبارت مرجع مشترک (`refunded_amount_expressions`).
- **NEW — تب عودت‌ها:** ستون «استفاده شده» با فیلتر دارد/ندارد (`u_sign`)، کارت KPI «جمع استفاده‌شده»،
  ستون جدید در خروجی اکسل، برچسب نوع در تاریخچه عودت پروفایل.
- **NEW — CustomerID پرونده در هدر پروفایل** (کنار کد مشتری، LTR) نمایش داده می‌شود.
- هارنس: Test 24 جدید (24 ادعا) + به‌روزرسانی Test 16/22/23 — TOTAL: **375 passed / 0 failed**؛
  رگرسیون SQLite: **141 / 0**.

## 0.8.62.10 — 2026-09-24

- Fix undefined refund alias in shared Refunds WHERE builder, including numeric search, refund amount operators and all balance filters.
- Centralize latest Excel refund and balance expressions across KPI, table and export. Preserve source_type=excel, descending id, LIMIT 1 and COALESCE only for balance/aggregates. No new JOIN, schema or import changes.
- Preserve positive/nonnegative >= 0 behavior and zero = 0.
- Fix tag filters in the shared builder using EXISTS (both = AND), removing the absent tag alias without adding row-level JOINs.
- Correct refund/tag ORDER BY aliases to the selected columns.
- Add executable regression and QA report. Historical suites are not shipped in the source ZIP and cannot be rerun from it.

## 0.8.62.9
- **CRITICAL FIX — KPI «همه عودت‌ها» = 28,876 (همه مشتریان CRM):** کوئری 0.8.62.8 هیچ predicate جمعیت
  پایه نداشت. حالا WHERE شامل predicate پایه است: مشتریِ دارای تگ REFUND_CONFIRMED/REFUND_REPORTED
  یا رکورد عودت قطعی اکسل (EXISTS — بدون JOIN fan-out). کارت «هر دو تگ» هم که OR شمرده می‌شد، حالا
  منطق AND واقعی است (دو تگ هم‌زمان).
- **FIX — یک سطر به ازای هر مشتری:** KPI و جدول دیگر JOIN تگ/عودت در سطح ردیف ندارند؛ تگ (قطعی اولویت)
  و مبلغ آخرین عودت اکسل با ساب‌کوئری LAST-ID در SELECT می‌آیند → COUNT(*) = COUNT(DISTINCT مشتری)
  و SUM ها دیگر دوبرابر نمی‌شوند (رفع انسداد مقادیر در 0.8.62.8).
- **NEW — همه کارت‌های KPI = تعداد + مبلغ + tooltip «؟»:** مبلغ هر کارت از جمعیتِ همان کارت با
  SUM CASE داخل همان کوئری: قطعی = جمع آخرین عودت قطعی (427,820,107,858)، همه = جمع همان جمعیت،
  هر دو = جمع مشتریان دو-تگ، مانده منفی = جمع مانده‌های منفی (39,036,465,003-)، اعلامی = 0
  (مبلغ قطعی ندارد). کارت‌های جمع هم مبلغ + tooltip دارند. فرمول Last-Refund دست‌نخورده.
- **NEW — خروجی اکسل (⤓ خروجی اکسل):** XLSX نتایج فیلترشده فعلی با همان WHERE/سورت لیست —
  کوئری واحد مرجع (`refunded_query_parts` + `refunded_fetch_rows`) بین لیست/KPI/خروجی؛ سقف سخت
  50,000 سطر؛ nonce-guarded (`bcrm_rf_export`) + بررسی مجدد کپ؛ fallback CSV با UTF-8 BOM اگر
  ZipArchive نبود؛ نویس XLSX مینیمال با ZipArchive (inline strings، بدون وابستگی خارجی).
- هارنس: Test 23 جدید (۲۰ ادعا) — TOTAL: 348 passed / 0 failed.

## 0.8.62.8
- **REDESIGN — صفحه «عودت‌ها» از پایه بازطراحی شد (UI/UX فقط؛ منطق کسب‌وکار دست‌نخورده):**
  - **فیلتر ستونی اکسلی واقعی:** هر سرتیتر جدول دکمه فیلتر مستقل ▾ دارد که یک popover باز می‌کند:
    ستون‌های متنی (مشتری/CustomerID/موبایل) جست‌وجو + حالت «شامل/دقیقاً برابر»؛ ستون‌های عددی
    (فاکتورها/مبلغ عودت/مانده) عملگرهای اکسلی برابر/بزرگ‌تر/بزرگ‌تر-مساوی/کوچک‌تر/کوچک‌تر-مساوی/بین
    با دو مقدار؛ مانده میان‌بر منفی/مثبت/صفر هم دارد؛ ستون تگ‌ها انتخاب قطعی/اعلامی/هر دو/بدون تگ.
    هر پنل مرتب‌سازی صعودی/نزولی و «حذف فیلتر این ستون» را هم دارد.
  - **سرور-ساید کامل:** هیچ فیلتری در JS انجام نمی‌شود؛ popover فقط URL را می‌سازد و WHERE همه
    فیلترها در SQL اعمال می‌شود (پارامترها: `tag_mode`, `inv_op/inv_a/inv_b`, `r_op/r_a/r_b`,
    `b_op/b_a/b_b`, `b_sign`, `name_q/code_q/phone_q + _m`, `r_q`, `r_sort`, `r_dir`, `r_p`, `r_pp`).
    ردیف‌ها با `SQL_CALC_FOUND_ROWS … LIMIT/OFFSET` — فقط صفحه درخواستی از دیتابیس می‌آید.
  - **KPI از یک کوئری تجمیعی SQL روی همان WHERE فیلترشده** — دیگر هیچ شمارشی در PHP انجام
    نمی‌شود؛ کارت‌ها همیشه دقیقاً همان جمعیت جدول را نشان می‌دهند.
  - **کارت‌های KPI کلیک‌پذیر:** همه عودت‌ها / قطعی (اکسل) / اعلامی / هر دو تگ / مانده منفی —
    کلیک فیلتر متناظر را اعمال می‌کند، کلیک دوباره حالت active را برمی‌دارد؛ کارت فعال با حاشیه
    آبی مشخص می‌شود؛ آیکن «؟» هر کارت tooltip توضیح دارد.
  - **۴ کارت جمع جدید:** جمع مانده / جمع فاکتورها / جمع عودت قطعی.
  - **سرتیتر صفحه:** «عودت‌ها» با زیرعنوان؛ نوار ابزار جست‌وجو + «فیلتر فعال: N» با چیپ‌های توضیح +
    «پاک کردن همه فیلترها» (جست‌وجو/فیلترها/sort را یک‌جا پاک می‌کند).
  - **Sort سه‌حالته:** کلیک اول صعودی، دوم نزولی، سوم reset (بدون فلش) — فلش ↑/↓ روی ستون فعال.
  - **نرمال‌سازی عدد:** ارقام فارسی/عربی و جداکننده هزارگان (، ٬ ,) هم در JS و هم در PHP پذیرفته می‌شود.
  - **حالت فعال ستون‌ها:** سرتیتر ستون فیلترشده با نقاط زیرخط و دکمه فیلتر آبی مشخص می‌شود.
  - CSS بازطراحی‌شده (روشن/تاریک، ریسپانسیو 8/4/3/2 ستونه) و نوار فیلتر قدیمی حذف/غیرفعال شد.
- **FIX — انسداد KPI قبلی:** تعداد قطعی/اعلامی/هر دو حالا SQL واقعی است (قبلاً PHP-side روی ردیف‌های فیلترشده بود).
- هارنس: Test 20/22 بازنویسی + ۲۵ ادعای جدید — TOTAL: 324 passed / 0 failed.

## 0.8.62.7
- **NEW — فیلتر ستونی اکسلی در تب «عودتی‌ها»:** نوار AutoFilter زیر سرتیتر فهرست؛ انتخاب «مانده:
  منفی/مثبت/همه» (لیست بازشو) و بازه «فاکتور از/تا» (عدد، با اعمال‌کردن دکمه). همه ورودی‌های HTTP
  allowlist/اعتبارسنجی شده‌اند (`f_balance`, `f_inv_min`, `f_inv_max`) و با گارد isset+is_string حتی با
  ورودی آرایه‌ای هم Warning نمی‌دهند. فیلترها بعد از محاسبه مانده و قبل از KPI/صفحه‌بندی اعمال می‌شوند؛
  یعنی کارت‌های KPI، شمارنده «مانده منفی» و صفحه‌بندی همه از مجموعه فیلترشده محاسبه می‌شوند. پارامترها
  در sort، صفحه‌بندی و انتخاب «در صفحه» حفظ می‌شوند؛ «حذف همه فیلترها» یک‌جا همه را پاک می‌کند. CSS روشن/تاریک.
- **NEW — جست‌وجوی عددی در مبالغ:** اگر عبارت جست‌وجو صرفاً عدد باشد (مثلاً 105050000)، ستون‌های
  «مجموع فاکتورها» و «مانده» هم match می‌شوند (علاوه بر نام/CustomerID/موبایل).
- هارنس: Test 22 (۱۳ ادعا) — TOTAL: 310 passed / 0 failed.

## 0.8.62.6
- **CRITICAL FIX — Fatal DivisionByZeroError در تب عودتی‌ها:** الگوی `in_array((int)($_GET['r_pp'] ?? 25), …)`
  وقتی پارامتر در URL نبود، داخل in_array به ۲۵ می‌رسید ولی بعداً `(int)$_GET['r_pp']` روی کلید
  ناموجود → Warning + 0 → تقسیم بر صفر در صفحه‌بندی. FIX: خواندن با `isset` قبل از allowlist.
- **NEW — دسترسی‌های per-user:** در «کاربران و دسترسی‌ها» برای هر کاربر پرتال دکمه «دسترسی‌ها»
  اضافه شد؛ مدیر می‌تواند دسترسی‌های اضافی را تیک بزند/بردارد (مشاهده تب عودتی‌ها، ثبت عودت،
  عودت وجه، گزارش‌ها، ایمپورت، مدیریت پرونده‌ها). فقط capهای لیست grantable حذف می‌شوند —
  دسترسی‌های نقش اصلی هرگز دست نمی‌خورند؛ همه تغییرات در audit_log ثبت می‌شود
  (`portal_user_caps_updated`). کاربران wp-admin از این پنل مستثنی‌اند.
- **NEW — cap سبک `bcrm_view_refunded`:** فقط تب «عودتی‌ها» + پروفایل مشتریان عودتی‌دار را باز
  می‌کند؛ صفحه آپلود اکسل همچنان با `bcrm_refund_register` محافظت می‌شود.
- **NEW — سرپرست عودت وجه** حالا عضو نقش خودش `bcrm_refund_register` دارد (تب عودتی‌ها + پروفایل
  عودتی‌ها)؛ نصب‌های موجود از طریق مکانیزم upgrade (caps marker) خودکار می‌گیرند.
- هارنس: Test 21 (۱۱ ادعا) — TOTAL: 296 passed / 0 failed.

## 0.8.62.5
- **NEW — تب «عودتی‌ها» پرتال با تجربه اکسلی:** ۶ کارت KPI روی کل مجموعه فیلترشده (نه فقط صفحه جاری):
  همه عودتی‌ها / قطعی / اعلامی / هر دو تگ / جمع مانده / تعداد مانده منفی؛ سرتیترهای جدول حالا
  قابل sort هستند (کلیک → تغییر صعودی/نزولی با فلش)؛ جست‌وجوی متنی روی نام/CustomerID/موبایل؛
  و صفحه‌بندی ۲۵/۵۰/۱۰۰ با نمایش بازه «نمایش X تا Y از N مشتری». همه ورودی‌ها allowlist شده‌اند
  (`r_sort`، `r_dir`، `r_pp`، `r_p`، `r_q`) و مانده/تگ هر ردیف فقط یک‌بار محاسبه می‌شود (بهینه‌سازی
  نسبت به نسخه قبل که per-row دوبار کوئری می‌زد). CSS روشن/تاریم هر دو پوشش داده شد.
- هارنس: Test 20 (۱۰ ادعا) — TOTAL: 286 passed / 0 failed.

## 0.8.62.4
- **FIX — Warning «Undefined property: stdClass::$actor_name»** (خط 1739 صفحه ثبت عودت‌ها):
  جدول «۱۰۰ ثبت اخیر» ستون ثبت‌کننده را نمایش می‌داد ولی کوئری آن `display_name` کاربر را
  join نمی‌کرد. FIX: `LEFT JOIN {$wpdb->users}` با alias `actor_name` + رندر با `?? '-'`
  (بدون warning حتی برای رکوردهای بدون کاربر). گارد defensive در تب «عودت‌ها» پروفایل هم اضافه شد.
- هارنس: 276/0 | roundtrip 20/0 | handoff 23/0 | workflow-fixes 28/0 — php -l و node --check: CLEAN.

## 0.8.62.3
- **FIX — «شما اجازهٔ دسترسی به این برگه را ندارید» برای Administrator:** ریشه مشکل نصب‌های
  موجود بود که نقش administrator آن‌ها **قبل از** معرفی `bcrm_refund_register` ساخته شده بود؛
  صفحه فقط در فعال‌سازی اولیه cap می‌داد. راه‌حل: نشانگر `biawin_crm_caps_marker` (v3) +
  re-grant روی `admin_init` + اتحاد همه capهای نقش‌ها برای administrator (فقط-افزایشی؛ هیچ cap
  حذف یا نقشی reset نمی‌شود) — نصب‌های موجود بدون deactivate/reinstall دسترسی می‌گیرند.
- **ارتقای استاندارد وردپرس:** ZIP حالا دقیقاً در پوشه `biawin-crm-integration/` بسته می‌شود
  (شناسه پلاگین بدون شماره نسخه) تا Plugins → Add New → Upload Plugin آن را همان پلاگین
  شناخته و روی نسخه فعال replace کند — بدون deactivate دستی؛ پلاگین فعال می‌ماند.
- **مهاجرت ترتیبی:** `MIGRATIONS` (0.8.62.0، 0.8.62.2) با قفل `bcrm_upgrade_lock` (300s)،
  هر مهاجرت idempotent؛ شکست → نسخه DB جلو نمی‌رود و بار بعد retry امن است؛ `biawin_crm_db_version`
  جدا از `BIAWIN_CRM_VERSION` نگهداری می‌شود. هیچ داده‌ای (مشتری/پرونده/تگ/عودت/لاگ) حذف نمی‌شود.
- هارنس: Test 19 (۱۷ ادعا) — cap گاردهای صفحه/هندلر، caps marker، migration idempotent،
  ساختار ZIP، پیش‌فرض‌های امن agent. TOTAL: 276 passed / 0 failed.

## 0.8.62.2
- **معماری فقط-ادمین برای آپلود اکسل عودت:** صفحه «ثبت عودت‌ها» از ناوبری پرتال کارشناسان حذف
  شد و فقط در پیشخوان وردپرس (`/wp-admin/admin.php?page=biawin-crm-refund-register`، زیرمنوی
  CRM بیاوین) با دسترسی اختصاصی `bcrm_refund_register` در دسترس است؛ Administrator دسترسی کامل
  دارد و کارشناس تنها با تخصیص صریح cap می‌بیند (پیش‌فرض: به agentهای عادی داده نمی‌شود).
- تب «عودتی‌ها» پرتال (مشاهده/فیلتر) و گزینه «عودت شده است» نتیجه تماس دست‌نخورده ماندند —
  دو Flow (اعلام کارشناس → REFUND_REPORTED؛ ادمین/اکسل → REFUND_CONFIRMED + مانده) مستقل‌اند.
- هارنس: Test 18 (۱۱ ادعا) — گارد منوی ادمین، حذف از پرتال، حفظ تب عودتی‌ها، استقلال دو Flow.
  TOTAL: 259 passed / 0 failed.

## 0.8.62.1
- **FIX — فیلترهای عودت از HTTP واقعاً کار می‌کنند:** `sanitize_key()` وردپرس مقادیر را lowercase
  می‌کرد؛ چون allowlist بزرگ‌نویسی بود، فیلترهای `REFUND_REPORTED`/`REFUND_CONFIRMED`/`BOTH` در
  تخصیص شماره (GET+POST) و تب عودتی‌های پرتال همیشه بی‌صدا به `any` برمی‌گشتند و «همه مشتریان»
  نشان داده می‌شد. راه‌حل: هِلپر `Biawin_CRM_Refund_Register::sanitize_tag_filter()` که ورودی
  HTTP (هر اندامسی) را قبل از allowlist نرمال می‌کند؛ سه نقطه مصرف به آن متصل شد.
- **FIX — عودت اعلامی کارشناس در تب «عودت‌ها»:** ثبت تماس با «عودت شده است» حالا یک رکورد
  `source_type='call'` (بدون مبلغ → مانده دست‌نخورده) با نام کارشناس و تاریخ/ساعت در تاریخچه
  می‌نویسد؛ تب پروفایل منبع را «اعلام‌شده توسط کارشناس» نمایش می‌دهد. ذخیره مجدد همان تماس
  idempotent است (رکورد تکراری نمی‌سازد).
- FIX — نقش «ثبت‌کننده عودت اکسلی» (`bcrm_refund_register`) به `role_labels()` اضافه شد و
  در صفحه «کاربران پرتال» هنگام ساخت کاربر قابل انتخاب است.
- هارنس: Test 17 (۲۲ ادعا) — ماتریس کامل فیلتر HTTP، رکورد اعلامی، idempotency، مانده دست‌نخورده،
  لیبل نقش. TOTAL: 248 passed / 0 failed.

## 0.8.62.0
- **سامانه ثبت عودت‌ها مبتنی بر اکسل (ویژگی جدید):** صفحه جدید «ثبت عودت‌ها» با دسترسی مستقل
  `bcrm_refund_register`. سرتیترهای اکسل: `customer id` / `وضعیت عودت` (۰ یا ۱) / `مبلغ عودت`.
  هر ردیف وضعیت ۱ → تگ `REFUND_CONFIRMED` «عودت قطعی» + رکورد عودت با مبلغ. آپلود مجدد همان
  مشتری مبلغ را جایگزین می‌کند و رکورد قبلی در تاریخچه می‌ماند. ردیف‌های خراب/مشتری ناشناس
  با شماره ردیف اکسل گزارش می‌شوند — هیچ ردیفی بی‌صدا حذف نمی‌شود.
- **کارت «مانده» در پروفایل مشتری:** مجموع فاکتورها − آخرین مبلغ عودت قطعی؛ منفی قرمز نمایش داده می‌شود.
- **تب «عودت‌ها» در پروفایل:** تاریخچه کامل اعلامی/قطعی با مبلغ، منبع، ثبت‌کننده، Batch و ردیف اکسل.
- **نتیجه تماس «عودت شده است»:** گزینه جدید در outcomes؛ ثبت آن توسط کارشناس → تگ `REFUND_REPORTED`
  «عودت اعلامی» + رکورد audit با نام کارشناس. دو تگ از هم مجزا هستند و هر دو در پروفایل/لیست‌ها دیده می‌شوند.
- **تب پرتال «عودتی‌ها»:** فهرست مشتریان دارای تگ عودت با فیلتر اعلامی/قطعی/هر دو + مانده هر مشتری.
- **تخصیص شماره:** فیلتر «وضعیت عودت» (قطعی/اعلامی/هر دو/همه) + ستون وضعیت عودت در جدول تخصیص؛
  هم مسیر Reporting-Adapter و هم مسیر انتخاب دستی/خودکار تخصیص فیلتر را رعایت می‌کنند.
- جدول جدید `wpx_bcrm_refund_registers` در Activator + نقش جدید «ثبت‌کننده عودت اکسلی».
- هارنس: Test 16 با ۲۹ ادعا (ایمپورت، تگ‌ها، مانده، جایگزینی، لاگ، canonical header، status parser).

## 0.8.61.5
- **گارد وضعیت دیگر بی‌صدا رد نمی‌کند:** «پرونده در این وضعیت قابل ارسال به مالی نیست» حالا وضعیت واقعی پرونده را به زبان ساده می‌گوید (مثلاً «قبلاً به مالی ارسال شده و در انتظار بررسی مالی است؛ ارسال مجدد لازم نیست») و ردشدن با case_id و وضعیت در لاگ ثبت می‌شود (log حادثه ۲۲ سپتامبر: ۱۸ فایل مرحله‌گذاری‌شده بدون هیچ ردی از finalize).
- همین شفافیت برای گارد ثبت فاکتور برگشتی هم اعمال شد.
- تست ۱۵ هارنس: رد گارد باید پیام توضیحی + رکورد audit_log داشته باشد و فایل‌های مرحله‌گذاری‌شده برای تلاش مجدد باقی بمانند.

## 0.8.61.4
- **رفع آخرین مسدودکننده انتشار — ثبت ردیف‌به‌ردیف فیش‌ها:** اگر کاربر همان فایل را در چند ردیف می‌گذاشت (لاگ ۱۷:۳۳ سرور)، هر تکرار در finalize نادیده گرفته می‌شد و فقط یک ضمیمه با مبلغ ردیف اول ثبت می‌شد؛ بقیه مبالغ بی‌صدا گم می‌شدند و پرونده گیر می‌کرد. حالا **هر ردیف ارسالی با مبلغ خودش** ثبت می‌شود و duplicate_count فقط عدد گزارشی است.
- finalize مرحله‌گذاری اکنون از `media_handle_sideload()` (مسیر بومی وردپرس برای فایل‌های روی دیسک) با یک کپی موقت برای هر ردیف وارد می‌کند — `media_handle_upload()` در وردپرس واقعی به‌خاطر `is_uploaded_file()` هر ردیف مرحله‌گذاری‌شده را رد می‌کرد (شیم هارنس کور بود و این را نمی‌دید).
- مسیر inline هم تکرار محتوا را دیگر نمی‌پرد؛ هر ردیف با مبلغ خودش ذخیره می‌شود.
- هارنس: شیم `media_handle_upload` گیت `is_uploaded_file` دارد؛ شیم‌های `media_handle_sideload` و `wp_tempnam` اضافه شد؛ تست‌های تکراری به معناشناسی ردیف‌به‌ردیف بازنویسی شد.

## 0.8.61.2
- **CRITICAL FIX (production incident, real host log captured):** every staged receipt upload died with
  `PHP Fatal error: wp_handle_sideload(): Argument #1 ($file) cannot be passed by reference` → HTTP 500
  → "هیچ داده‌ای ثبت نشده است". Root cause: `wp_handle_sideload()` takes its `$file` argument BY REFERENCE
  (it mutates the entry); `stage_entry()` passed a literal array, which is illegal in PHP 8. Fixed by
  assigning the entry to `$sideload_file` first. This was the true root cause of BOTH the 0.8.61.0
  "18 of 18 failed" incident AND the first upload attempt after 0.8.61.1.
- Harness hardening: the local `wp_handle_sideload` shim now matches the REAL WordPress by-reference
  signature, so any future by-value call fatals in the harness exactly like production instead of hiding.
- Suite: 191 passed / 0 failed (release blocker 20/20/20/20, 28-file staged batch PASS, Test 14 auth PASS).

## 0.8.61.1
- **Fixed the "18 of 18 receipts failed to upload" incident.** Root cause: portal pages served from hosting page cache (LiteSpeed/CDN) carry an expired `wp_create_nonce`; `check_ajax_referer` then rejected EVERY per-file stage request uniformly, and the JS surfaced only a generic "upload failed" alert. The layered auth (`authorize()`) now accepts a valid logged-in session plus a matching site token even when the localized nonce is stale, and refuses guests with an actionable 401 — so a cached page can no longer kill the whole batch, while anonymous abuse is still blocked.
- New `bcrm_stage_config` AJAX endpoint (priv + nopriv, `nocache_headers`) returns a fresh nonce + token; the JS auto-refreshes its upload config on any 401/403/`code=auth` response and retries.
- Per-file resilience: each staged file now retries up to 3 times (400ms/800ms backoff) on transient failures; on exhausted retries the alert shows the FIRST server reason verbatim (file name + exact message) instead of a count-only generic message.
- Diagnostics: `stage_request_received` / `stage_auth_token_fallback` / `stage_auth_rejected` (with Content-Type) / `stage_no_payload` / richer `stage_sideload_failed` log lines make hosting-side blockers (mod_security, POST body filters) visible in the plugin log.
- Nopriv stage requests now get structured JSON with real HTTP status codes instead of WordPress's bodyless `0`/`-1` die.
- Runtime harness Test 14 covers the full incident signature: 18 files staged under a stale nonce all stage and all persist; wrong-token → 403; guest → 401 with re-login guidance; config endpoint returns fresh nonce + token. Suite: 191 passed / 0 failed.

## 0.8.61.0
- **The receipt upload count limit is REMOVED, not just reported.** The browser now stages every receipt file individually (one file per AJAX request to `bcrm_stage_receipt`) before the final submit, so the host's per-request multi-file cap (`max_file_uploads`, default 20) and `post_max_size` can no longer truncate the batch — a 28-file (or larger) batch fully persists.
- New `Biawin_CRM_Receipt_Staging`: staged payloads live in `wp_options` under a per-form token, keyed by SHA-256 content identity, with request-scoped dedup (same content staged twice reuses one id), a 6h TTL, and full on-disk cleanup on remove/consume.
- The final submit carries only staged ids + amounts (`staged_receipt_ids[]` / `staged_receipt_amounts[]`). `send_to_finance()` validates ids↔amounts 1:1, materializes each staged file through the exact same media + DB integrity path as before, and only then moves the case to `FINANCE_REVIEW_PENDING`.
- Unknown/missing staged ids or an id/amount count mismatch fail loud with FULL rollback of this request's rows and media; previous-round attachments are never touched. A repeated staged id counts as a duplicate (persisted once), mirroring the inline path.
- No row-count limit remains: the add-row cap, `is-cap-disabled` state, and pre-submit count warnings are gone; the receipt hint now documents the format/size rules and the automatic per-file upload behavior with a live progress indicator (`data-bcrm-stage-status`).
- Accepted formats widened to every image type WordPress knows (JPG, PNG, GIF, WebP, AVIF, HEIC/HEIF, BMP, TIFF, ICO) plus PDF (`RECEIPT_MIMES` shared by inline and staged paths).
- The legacy inline multi-file path is still fully guarded (canonical normalization, SHA-256 dedup, truncation detection, rollback) for non-JS clients and API flows.

## 0.8.60.1
- Pre-submit guard in the browser: the receipt form counts chosen files and blocks submission (with a clear Persian explanation) when the count exceeds the server's per-request file cap; the add-row button is disabled at the cap and refuses to add rows beyond it.
- The receipt form now displays the server's current simultaneous-file cap (`max_file_uploads`) as a visible hint so agents learn the limit before building a doomed 28-row request.
- When the server cap truncates a request anyway (28 declared → 20 received), the error now names the exact cause and includes step-by-step hosting-panel and `.user.ini` instructions (via `Biawin_CRM_Upload_Normalizer::server_limit_notice()`), plus the new exact-cap signature detection (`looks_like_max_uploads_cap`).
- `maxFileUploads` is localized to JS (`BiawinCRM` and `BCRM_ADMIN`) so guards never guess the limit.
- v0.8.60.0 already prevented partial persistence in this scenario; this release only improves prevention, messaging, and guidance.

## 0.8.60.0
- Fixed the multi-file receipt upload data integrity bug in the Deficiency → Finance workflow: 20 selected unique files now persist as exactly 20 unique attachments.
- Root cause: `send_to_finance()` re-indexed `receipt_amounts` with `array_values()` while `case_documents[]` kept original sparse request indexes, so after deleting middle rows amounts were matched to the wrong files, rows without an amount+file pair were silently skipped, and later files were never processed; the dedup `continue` additionally dropped rows instead of only the duplicate file.
- Added `Biawin_CRM_Upload_Normalizer`: canonical `$_FILES` normalization preserving ORIGINAL request keys, explicit rejection of malformed entries, `max_file_uploads` / `post_max_size` truncation detection, SHA-256 content identity, and safe diagnostic logging (counts/indexes only).
- `save_uploaded_document()` now selects the uploaded file strictly by the row's original request key with no positional fallback and no `$_FILES` swapping; PHP upload errors are mapped to explicit failures.
- Duplicate receipts are rejected per-row by content hash; two different files sharing one filename are both accepted.
- A failing receipt row rolls back all attachment DB rows and media files created in the same request; previous Finance-round attachments are never deleted or overwritten.
- On any partial receipt persistence failure the case is NOT moved to `FINANCE_REVIEW_PENDING` and a meaningful error with upload accounting (`received/valid/uploaded/persisted/duplicate/failed`) is returned.
- Kept the upgrade code-only with no schema migration, customer merge, invoice migration, Guard change, Run #7 continuation, or Run #8 implementation.

## 0.8.59.9
- Hardened the deficiency/finance round-trip workflow for receipt uploads and returned cases.
- Fixed scalar/malformed `case_documents` detection so one uploaded receipt is not treated as present for every receipt row.
- Added per-request duplicate receipt suppression using file signatures before `media_handle_upload`.
- Allowed `RETURNED_TO_DEFICIENCY` cases to be sent back to financial approval after follow-up work.
- Added a returned-invoice action inside the deficiency case detail page so agents can add a finance-return invoice without leaving the case.
- Kept the upgrade code-only with no schema migration, customer merge, invoice migration, Guard change, Run #7 continuation, or Run #8 implementation.

## 0.8.59.8
- Hardened deficiency finance handoff with receipt uploads so a successful receipt upload continues into `FINANCE_REVIEW_PENDING`.
- Auto-detects receipt sending from uploaded files or receipt amounts, protecting against missing checkbox payloads in multipart submits.
- Processes repeatable `case_documents[]` file indexes defensively and requires both amount and file for each submitted receipt row.
- Kept the upgrade code-only with no schema migration, customer mutation, invoice mutation, Guard change, Run #7 continuation, or Run #8 implementation.

## 0.8.59.7
- Renamed the deficiency workflow action from `ارسال به تایید مالی با فیش` to `ارسال به تایید مالی`.
- Added an `ارسال فیش` checkbox; receipt upload rows open only when this checkbox is selected.
- Allowed finance handoff without receipt uploads unless receipt sending is explicitly selected.
- Added `وضعیت عودت: عودت شده` metadata/tagging on finance handoff and exposed it in timeline, report KPI, CSV export, and REST export.
- Kept the upgrade code-only with no schema migration, customer mutation, invoice mutation, Guard change, Run #7 continuation, or Run #8 implementation.

## 0.8.59.6
- Fixed receipt add/remove click handlers when the main frontend workflow initializer runs before `portal-fixes.js`.
- Added receipt-control wiring to `frontend.js` and guarded duplicate binding with `data-receipts-ready`.
- Kept the upgrade code-only with no data mutation, Guard change, Run #7 continuation, or Run #8 implementation.

## 0.8.59.5
- Tightened the deficiency send-to-finance receipt UI so repeatable receipt rows remain aligned and compact.
- Added a global portal font setting and a per-user portal font switcher.
- Applied Persian-friendly font stacks across portal shells, embedded admin pages, form controls, and workflow UI.
- Kept the upgrade code-only with no data mutation, Guard change, Run #7 continuation, or Run #8 implementation.

## 0.8.59.4
- Added deletion for non-admin portal users, with nonce protection, self-delete protection, and audit logging before deletion.
- Fixed front-portal deficiency search URLs using `s` by moving the value into `bcrm_s` so WordPress does not treat the portal request as a site search.
- Changed deficiency/finance assignment return flow to require a selected agent and only return selected assigned cases belonging to that agent.
- Added multi-receipt send-to-finance support with repeatable receipt amount/file rows and per-file attachment persistence.
- Reworked deficiency/finance KPI cards into status-specific counts with `?` help tooltips.
- No customer data migration, deficiency case data migration, assignment migration, Guard change, Run #7 continuation, or Run #8 implementation.

## 0.8.59.3
- Deficiency Supervisor Assignment UI Hardening: the assignment page now supports both assigning ready deficiency cases to agents and returning already assigned cases.
- Deficiency assignment rows show the primary normalized mobile number, matching the supervisor's number-based workflow.
- The return-assignment button no longer depends on selecting an agent.
- Code-only upgrades from v0.8.58.0, v0.8.59.0, v0.8.59.1, and v0.8.59.2 to v0.8.59.3 remain schema-safe and repair specialist capabilities.
- No customer data mutation, deficiency case data migration, assignment migration, Guard change, Run #7 continuation, or Run #8 implementation.

## 0.8.59.2
- Deficiency Role Capability Sync Hardening: role/cap definitions now sync through a dedicated idempotent activator method.
- Code-only upgrades from v0.8.58.0, v0.8.59.0, and v0.8.59.1 to v0.8.59.2 repair specialist capabilities before returning.
- Portal user creation repairs roles before inserting the user, so new `bcrm_deficiency_agent` users can see and process رفع ناقصی like the seeded default agent.
- No customer data mutation, deficiency case mutation, assignment mutation, Guard change, Run #7 continuation, or Run #8 implementation.

## 0.8.59.1
- Release B.2.3 Workload/Kartable Production Hardening: closed independent audit blockers in migrated workload and Kartable read paths.
- Moved filter-current Kartable workload counts and rows to Reporting Consumer Adapter / Canonical Assignment SOT.
- Added explicit proven reporting scope metadata for management aggregate methods and fail-closed denial for unsupported tampered department/organization requests.
- Added shared total_count semantics for card/list reconciliation and pagination safety.
- Fixed trusted Guarded workload rendering in the per-agent workload table.
- Added v0.8.59.1 hardening tests.
- No schema mutation, domain mutation, Guard change, assignment mutation, Run #7 continuation, or Run #8 implementation.

## 0.8.59.0
- Release B.2.3 — Workload and Kartable SOT Migration: migrated assignment/workload dashboard and Kartable consumers to Reporting Consumer Adapter/SOT.
- Workload cards, per-agent rows, recent active assignment rows, and portal assignment-related dashboard KPIs now use newest-active assignment semantics.
- Guarded workload remains separately visible to managers and excluded from ordinary expert workable workload.
- Kept CSV/Excel exports, REST reporting, expert performance, detailed finance reports, detailed case reports, Guard, Run #7, and Run #8 out of scope.
- No schema mutation, domain mutation, Guard change, or assignment mutation was added for this read-path migration.

## 0.8.58.0
- Release B.2.2 — Management Reports SOT Migration: migrated Management Reports summary consumers to Reporting Consumer Adapter/SOT.
- Replaced report summary raw customer counts and `customers.total_paid` truth with `NORMAL_CANONICAL` population and `ACTUAL_PAID_LIVE` invoice evidence.
- Added cross-surface reconciliation tests for Main Dashboard and Management Reports shared metrics.
- Kept CSV/Excel exports, REST reporting, expert performance, workload/Kartable, and detailed case/finance reports out of scope.
- No schema mutation, domain mutation, Guard change, Run #7 continuation, or Run #8 implementation.

## 0.8.57.1
- Hardened Release B.2.1 for Production after independent audit.
- Replaced the dashboard actual-paid KPI read path with a database-side `management_actual_paid_total` aggregate that avoids full `NORMAL_CANONICAL` ID materialization and preserves `ACTUAL_PAID_LIVE` semantics.
- Bounded dashboard failure notes so management users see safe status messages rather than raw adapter/database errors.
- Added code-only upgrade gates for direct `0.8.55.2`, `0.8.56.0`, `0.8.56.1`, and `0.8.57.0` upgrades to `0.8.57.1`.
- Removed inherited customer/reconstruction JSON artifacts from the Production package; `data/blacklisted-customer-ids.txt` remains as the official runtime blacklist dependency.
- Added v0.8.57.1 hardening, render, upgrade, performance, and package-hygiene regression coverage.

## 0.8.57.0
- Migrated only the main management dashboard KPI cards to the shared Reporting Consumer Adapter.
- Replaced raw dashboard `customers`/`invoices` count queries with read-only Population, Financial, Assignment, and Case SOT calls.
- Exposed `NORMAL_CANONICAL`, Guarded, Blacklisted live, and Unresolved customer populations as separate dashboard cards.
- Kept exports, REST APIs, agent dashboards, workload pages, case detail reports, Guard, Run #7, and Run #8 untouched.
- Added B.2.1 dashboard migration report and QA coverage for KPI parity, population separation, financial source, Guard visibility, and no-mutation boundaries.

## 0.8.56.1
- Hardened the Release B.1 adapter after independent audit.
- Fixed `customer_population_ids(NORMAL_PLUS_VISIBLE_QUARANTINE)` and `customer_population_count(NORMAL_PLUS_VISIBLE_QUARANTINE)` so public results preserve `normal_canonical`, `guarded`, `blacklisted_live`, and `unresolved` segment metadata while keeping aggregate visible IDs deduplicated.
- Documented all public adapter methods, including `reset_request_memo` and `public_methods`.
- Removed inherited `data/historical-identity-baseline.ndjson` from the Production release artifact after confirming references are limited to historical/manual maintenance tooling rather than normal B.1 runtime.
- Kept `0.8.55.2 -> 0.8.56.1` and `0.8.56.0 -> 0.8.56.1` code-only upgrade paths with no schema/domain/Guard/customer mutation.
- No B.2 migration, no reporting consumer migration, no Run #7 continuation, and no Run #8 implementation.

## 0.8.56.0
- Added Release B.1 Reporting Consumer Adapter Foundation as one shared read-only facade for future B.2-B.7 reporting migrations.
- Adapter returns structured `status`, `trusted`, `population`, `freshness`, `source`, `generated_at`, `data`, `value`, and `error` metadata instead of trusted scalar business truth.
- Added population, financial batch, assignment workload, event/audit separation, case context, export sanitizer, filter validation, scope checks, request-scoped memoization, and approved-paid unresolved contract behavior.
- Added B.1 behavioral/adversarial regression harness and release documentation.
- No legacy dashboard/export/REST/cartable/report consumer is migrated in this release.
- No schema migration, domain mutation, Guard/blacklist/customer/CustomerID/invoice/phone/assignment/case/history mutation, Run #7 continuation, or Run #8 implementation.

## 0.8.55.2
- Fixed MariaDB compatibility for Reporting SOT financial freshness by replacing the `dec` alias and boolean SUM expressions with explicit aliases and CASE aggregates.
- Fixed event double-count validation to use the real `activities.activity_type` event column via read-only column compatibility detection.
- Hardened financial and event-risk query failures so failed validation returns untrusted null metrics instead of zeros.
- Added separate `core_source_of_truth_status` and `validation_execution_status` values so query failures degrade validation without falsely marking the whole page healthy.
- Kept `0.8.55.1 -> 0.8.55.2` code-only with no schema/domain/Guard/customer mutation.

## 0.8.55.1
- Hardened Reporting Source-of-Truth population handling so unavailable Guard or blacklist evidence blocks trusted `NORMAL_CANONICAL` reporting instead of being treated as zero exclusions.
- Added explicit Guard/blacklist source status, canonical trust metadata, divergence counters, and unassigned `NORMAL_CANONICAL` assignment reporting.
- Kept the production upgrade path from `0.8.54.2` to `0.8.55.1` code-only with no schema/domain mutation.
- Added expanded fail-closed regression/adversarial coverage for Guard, blacklist, canonical population, divergence, assignment, Run #7 terminal metadata, and Run #8 absence.

## 0.8.55.0
- Added Reporting Source-of-Truth Foundation services for population, live financial truth, canonical active assignment semantics, and read-only validation.
- Added admin read-only Reporting SOT validation page with population, financial freshness, assignment integrity, case ownership, event double-count, and migration-state checks.
- Established explicit Guard/blacklist population semantics and Frozen Run #7 historical-only reporting rule.
- Added isolated reporting foundation regression harness.
- No Run #8, no Guard mutation, no customer migration, no schema change, and no production-domain data mutation.

## 0.8.54.2
- Hardened v0.8.54.x read-only reconciliation release safety.
- Fixed historical manual merge detection so guarded customers are detected as merge primary or inside `merged_customer_ids`, independent of chunk membership or Run #7 cutoff.
- Added CSV formula-injection neutralization at the CSV export boundary only.
- Changed phone ownership evidence from first owner to complete deterministic owner sets.
- Hardened invoice/phone `NOT_APPLICABLE` semantics so linked evidence is evaluated instead of being treated as automatic N/A.
- Added and executed complete classification, merge, phone owner, CSV, and N/A regression coverage.

## 0.8.54.1
- Hardened v0.8.54 invoice and phone evidence consistency checks.
- Replaced placeholder count checks with read-only evidence states: PASS, FAIL, UNKNOWN, and NOT_APPLICABLE.
- Added invoice/phone reason codes, compact CSV fields, structured JSON evidence details, aggregate evidence metrics, and evidence fields in the deterministic reconciliation hash.
- SECOND_PASS_SAFE now fails closed when invoice or phone evidence is FAIL or UNKNOWN.

## 0.8.54
- Added v0.8.54 Post-Run-7 Remaining Blacklist Read-only Reconciliation.
- Derives scope from current Readiness Guard rows instead of replaying the full frozen preflight.
- Correlates remaining guarded rows with Frozen Plan v0.8.53.2, Run #7 quarantine details, original MANUAL_REVIEW protection, live assignments, human activity evidence, manual merge evidence, target state, and Guard invariants.
- Adds read-only CSV/JSON exports and deterministic reconciliation SHA-256 for a future planner input.
- Provides no commit, repair, migrate, re-key, reconstruction, Guard-sync, or Run #8 endpoint.

## 0.8.53.3.2
- Added Sequential Auto Runner for the frozen blacklist final commit engine.
- Preserves max-50 sequential chunks; no parallel requests.
- Stops immediately on HTTP/AJAX failure, run/plan mismatch, non-advancing processed state, profile rollback, or unknown quarantine reason.
- ACTIVE_ASSIGNMENT_CONFLICT and TARGET_ROW_MISSING remain expected fail-closed quarantine reasons.
- Manual Stop takes effect after the currently running chunk completes.

# v0.8.53.2 — Missing Target Resolution + Frozen Canonical Plan

- Adds explicit `CREATE_MISSING_VALID_TARGET` modeling for authoritative corrected destinations whose internal customer row is absent.
- Preserves composite ordering: create/migrate/verify Source person first, then re-key the current blacklisted row for the incoming person.
- Keeps `MANUAL_REVIEW` quarantined.
- Adds deterministic Plan SHA256.
- Frozen CSV/JSON are downloadable only from the exact PASS snapshot; export does not recompute live decisions.
- No domain commit engine is included.


## 0.8.53 — Production Readiness + Blacklist Final Reconciliation
- Added official Blacklist Readiness Guard and control-plane quarantine table.
- Added read-only production readiness audit and final reconciliation CSV export.
- Blocked ordinary-agent work/new assignment on quarantined identities while preserving manager inspection.
- Added per-agent cartable/workload counts for supervisors.
- Added agent-side invoice phone append with full audit evidence and conflict blocking.
- Made invoice total explicit from actual attached invoices.
- No bulk CustomerID rekey in this version.
## 0.8.47.3
- Added transaction-time row revalidation after `SELECT ... FOR UPDATE` for every planned legacy record.
- Legacy projected hashes are rechecked under lock; any drift aborts with `LEGACY_HASH_CHANGED_UNDER_LOCK`.
- Destination phone/invoice evidence is locked and revalidated before mutation.
- Source/target active assignments are locked and conflicts are rechecked inside the transaction.
- No expansion of pilot scope: still exactly 10 profiles / 20 legacy records.

## 0.8.47.2

- Fix false pilot Hash Fail caused by generated DB columns such as `assignments.active_customer_guard`.
- Compare legacy rows against the exact frozen manifest field set.
- Prevent rollback from trying to write generated columns.
- Keep all identity/activity/merge/assignment safety guards unchanged.

## 0.8.47.1
- Diagnostic-only hotfix: keeps hash guard strict, embeds exact Golden expected rows for the 20 pilot records, reports field-level differences on hash mismatch, and treats assignment completed_at as a post-Golden timestamp signal.
- No relaxation of commit safety.

## 0.8.41
- Added official 48,331 CustomerID blacklist as a permanent hard constraint.
- Added read-only current-state blacklist reconciliation and dependent-record counts.
- Corrected-workbook targets that are blacklisted are now rejected and flagged for replacement ID allocation.
- No repair/write is performed in this release.

## 0.8.40
- Added a read-only Historical Identity Audit based on the healthy 2026-08-18 CRM backup and the final CustomerID correction workbook.
- Added a production safety lock that blocks real ownership-correction execution and unapproved-invoice cleanup execution by default while leaving previews and normal specialist workflows available.
- Audit detects deterministic/ambiguous identity mappings, Complete-profile regressions, reused internal customer rows, stranded declared-paid values, missing destinations, name mismatches, and historical operational-history counts.
- Historical baseline is streamed from NDJSON in batches so the full dataset is not loaded into PHP memory.
- No historical reconstruction/write is performed in this release; v0.8.40 is intentionally audit-only.

## 0.8.39
- Ownership-correction preview now allows execution when rows are already attached to the requested CustomerID, so identity repair is no longer blocked by `will_move = 0`.
- Identity repair now detaches stale non-manual phone ownership that is not present in the profile's current `invoice_phones`, preventing phone search from resolving to an old profile after invoice ownership was corrected.
- Manual and manual-invoice-bundle phone rows are preserved during ownership repair.

# Changelog

## v0.8.38

Fixed:
- Invoice ownership correction now repairs customer identity from the invoices owned by each corrected profile.
- Re-running the correction file repairs already-correct invoice rows too, restoring customer display names, sales manager values, and selectable phone numbers from invoice data.
- Phone ownership is synchronized from invoice phones after correction so search and call-result forms use the corrected customer profile.

## v0.8.37

Changed:
- Comprehensive customer Excel/CSV exports now output one row per invoice, repeating the same CustomerID and customer fields for every invoice code.
- Added invoice-level export columns, including invoice code, invoice title, invoice amount, seller, call center, invoice sales manager, invoice status, invoice date, prepayment approval status, and excluded-from-total flag.

## v0.8.36

Changed:
- Marked the plugin as a private/custom plugin with `Update URI: false` so WordPress does not try to resolve it through WordPress.org plugin updates.

## v0.8.35

Changed:
- Financially unapproved invoice cleanup preview now runs in resumable batches to avoid nginx/PHP gateway timeouts during file analysis.
- Cleanup file selection no longer auto-submits the form; the operator starts preview explicitly with the review button.
- Preview must finish before real cleanup actions are enabled, so category counts are complete before execution.

## v0.8.34

Changed:
- Financially unapproved invoice cleanup execution now runs in resumable batches to avoid nginx/PHP gateway timeouts.
- Cleanup batch execution keeps remaining CustomerIDs in the saved job and shows a continue button until processing finishes.

## v0.8.33

Changed:
- Invoice ownership correction execution now runs in resumable batches to avoid nginx/PHP gateway timeouts.
- Batch execution keeps the remaining correction job in a transient and shows a continue button until all rows are processed.
- Subsequent ownership batches no longer create repeated backups unless the operator starts a fresh execution.

## v0.8.32

Added:
- Local/server file-path restore option for large CRM backup files that exceed PHP upload limits.

Fixed:
- Backup upload restore now reports the real PHP upload error before falling back to a generic invalid-file message.
- Backup restore form no longer requires browser upload when a local/server path is provided.

## v0.8.31

Added:
- Upload-and-restore support for CRM backup files, enabling live-site backups to be restored directly on a local test site.

Changed:
- Invoice ownership correction now creates a new Customer profile when the target CustomerID from the correction file does not exist.
- Invoice ownership correction keeps existing profiles, calls, assignments, and history in place; it only moves invoice ownership and recalculates affected totals.

## v0.8.30

Added:
- Invoice ownership correction page for previewing and executing `invoice_code -> CustomerID` repairs.
- Full CRM backup/export entry point from the invoice ownership correction workflow.

Changed:
- Ownership correction only changes invoice ownership and same-invoice service ownership; it does not move interactions, assignments, phones, or delete profiles automatically.

## v0.8.29

Changed:
- Corrective import identity logic now uses only CustomerID for profile/invoice ownership.
- In corrective import, phone 1 and phone 2 may still be saved when present, but neither is used to find or merge profiles.
- Corrective import UI text now clearly states that all phone-based identity matching is disabled.

## v0.8.28

Added:
- Separate corrective import menu for repairing profiles wrongly merged by shared placeholder second-phone values.

Changed:
- Corrective import opens with CustomerID-based repair mode enabled by default.
- The informational "no financially unapproved invoice" cleanup category is no longer executable because it does not change database rows.
- Cleanup execution now rejects no-op-only submissions with a clear message.

## v0.8.27

Added:
- Corrective import mode for fixing wrongly merged profiles by CustomerID.
- Corrective import ignores second phone numbers for profile linking so shared placeholder phones do not merge unrelated customers.

Changed:
- Financially unapproved invoice cleanup now shows a clear execution notice with selected categories and changed row count.
- Cleanup preview tokens are invalidated after a real execution to prevent accidental repeat execution.

## v0.8.26

Changed:
- Financially unapproved invoice cleanup now auto-builds the preview immediately after selecting an Excel/CSV file.
- Cleanup execution now runs from the same preview token, so the file does not need to be uploaded a second time.
- Admins can choose which preview categories should be executed: single-invoice profile deletion, multi-invoice total exclusion, and no-action report rows.

Fixed:
- Real cleanup reports now keep the original uploaded row and unique CustomerID counts.

## v0.8.25

Fixed:
- Financially unapproved invoice cleanup no longer crashes when a Customer profile has no display name.
- XLSX temp extraction now uses the WordPress uploads directory first, avoiding Windows TEMP permission warnings in LocalWP.
- Temporary XLSX cleanup is tolerant of already-removed or inaccessible temp folders.

## v0.8.24

Added:
- Full CRM backup and in-portal restore page for all Biawin CRM tables.
- Backup-before-import option on final import commit.
- Backup-before-cleanup option for financially unapproved invoice cleanup.

Fixed:
- Faster streaming import batches for large Excel files.
- Import preview now reads `C000001` style CustomerID ranges correctly.
- CRM backups skip generated database columns so restore can reinsert assignment rows safely.

## v0.8.23

Added:
- Read-only/execute upload tool for cleaning financially unapproved invoices by CustomerID Excel/CSV.
- Multi-invoice unapproved invoices can now remain visible in the profile while being excluded from profile totals.
- Invoice cleanup audit/activity logging and comprehensive export tagging for invoices excluded from totals.

Fixed:
- Customer total recalculation and reconciliation preview now ignore invoices intentionally excluded from totals.
- Data Audit invoice-sum integrity checks now respect excluded-from-total invoices.

## v0.8.22

Added:
- Read-only Data Audit page for validating live KPI counts, assignment state, contact status, and data integrity.
- Data Audit JSON export for offline review and support.

## v0.8.21

Fixed:
- Assignment page now shows how many profiles match the current filters, how many are still unassigned, and how many are already assigned.
- Empty assignment results now return to the assignment page with a clear warning instead of stopping on a WordPress error page.
- Direct single-customer assignment no longer applies the default "not contacted yet" filter unless a contact filter is explicitly posted.

## v0.8.20

Fixed:
- Assignment now has a contact-status filter and defaults to "not contacted yet" for fresh assignment batches.
- Assignment filtering is enforced in the save handler, so contacted customers cannot slip into default fresh assignment batches.
- Assignment tables and latest-batch details now show the latest contact status and the user who recorded it.

## v0.8.19

Fixed:
- Assignment agent selection is now rendered as clear checkboxes instead of an ambiguous multi-select list.
- Assignment success now records and displays which customers were assigned to which agents.
- The assignments page now shows the latest active assignments so previous batches can be traced immediately.

## v0.8.18

Fixed:
- Manual assignment now respects the selected agents and is no longer silently overridden by sales manager rules.
- Sales manager rule priority is no longer enabled by default in the assignment form.
- Sales manager rules are applied only when no manual agents are selected or when distributing across all active agents.

## v0.8.17

Fixed:
- Assignment return now supports selecting all active agents or multiple agents at once.
- Assignment return filters now include latest contact status, including "not contacted yet".
- Assignment return can be limited by minimum/maximum total paid amount.
- Assignment filters now submit to the correct assignments URL inside the portal shell.

## v0.8.16

Fixed:
- Workflow final-submit UI now shows only the selected deficiency/finance section instead of all sections at once.
- Workflow inputs now use consistent styled controls for text, money, date, file, select, and textarea fields.
- Workflow JS is loaded through the main portal/admin assets instead of relying on inline page scripts.
- Workflow form layout now uses the full portal width with readable grouped fields.

## v0.8.15

Added:
- Unified final-submit workflow forms for deficiency and finance case actions.
- Required receipt upload and receipt amount when sending a case to finance.
- Readable workflow timeline details instead of raw JSON.
- Finance approval rules for invoice/phone identifiers and final approved amounts.

Fixed:
- Deficiency cases are no longer created for differences within the +/- 500,000 toman tolerance.

## v0.8.14

Fixed:
- Portal workflow tabs now keep the correct `deficiency` and `financial` views instead of falling back to dashboard.

## v0.8.13

Fixed:
- Completed REST/API coverage for deficiency and finance workflow cases, assignment, detail, dashboard, timeline, attachment upload, and export.
- Finance workflow timeline now records exact result events before returning the case to deficiency.
- Workflow assignment supports multi-agent distribution and assignment return.
- Workflow case lists now have a department/status/date index for faster filtered screens.

## v0.8.12

Added:
- Deficiency and finance workflow case engine with state validation, assignment, audit trail, reports, and CSV export.
- Deficiency Supervisor/Agent roles and dedicated finance workflow capabilities.
- Customer completion source tracking for profiles completed after deficiency resolution.

Fixed:
- Incomplete customer sync now creates/updates a workflow case on the existing Customer instead of creating any duplicate customer.

## v0.8.11

Fixed:
- Dark mode readability for embedded admin tables, striped rows, table controls, and assignment/customer-service lists.

## v0.8.10

Fixed:
- Dark mode toggle no longer runs twice when both the base portal script and cache-proof fix script are present.
- Assignment return now supports agent, operational status, profile completeness, journey, and count filters.

## v0.8.9

Fixed:
- Added cache-proof portal CSS/JS assets so production sites load the dark-mode and assignment UI fixes even when older bundled assets are cached.
- Asset enqueue versions now include each file modification time.

## v0.8.8

Added:
- Customer phone detach
- Phone permission
- Sales manager assignment
- Duplicate assignment protection

Fixed:
- Customer-level sales manager storage during import.
- Upgrade migration for complaint ownership columns.
- Arabic digit normalization in money fields.
- Sales manager assignment rule now uses a dropdown populated from imported Excel data.
- Shared-phone supervisor guard message now clarifies it is based on cross-invoice repetition, not invoice phone count.
- Operational reports now include KPI amount splits, contact status counts, satisfaction summary, route totals/details, and service/promotion counts.
- Comprehensive Excel export now includes the portal-level customer, phone, invoice, interaction, service, complaint, refund, financial, assignment, and attachment summaries without wrap-text styling.
- Report and portal date/time labels now render in Jalali calendar using Iran time.
- Agent contact result dropdown now defaults to "انتخاب کنید" instead of auto-selecting "تماس موفق".
- Assignment return can now be run by selecting an agent, in addition to selected customer rows.
- Agent customer list now includes a "بدون وضعیت" contact-status tab for untouched profiles.
- Invoice/phone attach flow now allows legitimate multi-invoice customer connections and only blocks very high-frequency shared phones.
- Portal dark mode toggle added with readable table, form, card, and embedded admin-page styles.
- Assignment page UI refreshed with KPI cards, grouped controls, direct search, agent return, and cleaner execution controls.

## 0.8.45
- Added read-only Expert Intent & Manual Merge Reconciliation Audit.
- Added human-vs-system evidence classification and two reconciliation CSV exports.
- Manual merges are treated as protected identity decisions.

## 0.8.47.1
- Added Controlled Forward Reconstruction with a frozen 10-profile pilot.
- Added Golden-snapshot hash preconditions and live Final Safe Set revalidation.
- Added exact-record legacy migration for selected Activity/Assignment rows only.
- Added transactional journal, post-commit verification, all-or-nothing rollback and hash-guarded manual rollback.
- No bulk migration, invoice/phone ownership change, CustomerID rekey/create, or deleted-invoice restoration is enabled in this version.

## 0.8.53.1 — Canonical Final Reconciliation Plan
- Added a read-only canonical planner for the 6,821 current blacklisted internal customer rows.
- Fixes the v0.8.53 CSV ambiguity where one current row could appear twice because it simultaneously played a historical source role and a corrected-target role.
- Produces exactly one canonical decision per current internal row and explicitly models dual-role rows as `COMPOSITE_MIGRATE_SOURCE_THEN_REKEY_CURRENT` instead of silently dropping either role.
- Adds hard invariants for blacklisted final IDs, invalid existing targets, unauthorized final-ID collisions, duplicate source decisions, and unresolved decisions.
- No commit engine and no writes to CustomerID, customer, invoice, phone, history, assignment, merge, or readiness guard state.

## 0.8.53.3
- Added Frozen Blacklist Final Commit Engine pinned to v0.8.53.2 canonical plan SHA256.
- 6,593 auto-eligible profiles; 228 MANUAL_REVIEW profiles are never auto-committed.
- Max 50 profiles per request; profile-level transaction, row locks, live revalidation, journal, verification and quarantine-on-conflict.
- Added explicit Expert Readiness gate; Guard must remain enabled and ordinary access to quarantine must be zero.
- Added Missing Target creation using healthy historical baseline identity fields.
- Composite order is enforced: migrate operational history -> verify -> re-key blacklisted current row.
- audit_log and merge_events remain immutable evidence.


## 0.8.53.3.1
- MariaDB/WordPress control-table schema hotfix for the Frozen Blacklist Final Commit Engine.
- Removed `dbDelta()` from `bcrm_reconstruction_runs` and `bcrm_blacklist_final_journal` setup because one-line field definitions were misparsed into invalid ALTER TABLE statements.
- Existing control-plane tables are never altered by this hotfix; missing tables are created with explicit SQL only.
- Added fail-closed required-column validation before preflight/commit state access.
- Frozen Plan hash, 6,593 auto-eligible scope, 228 MANUAL_REVIEW guard policy, and all domain migration rules are unchanged.
