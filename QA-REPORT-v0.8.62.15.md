# QA Report — v0.8.62.15

**تاریخ:** 2026-09-27
**محدوده:** تخصیص دسته‌ای پرونده‌ها از فایل اکسل Customer ID در تب «تخصیص» Workflow رفع ناقصی (و معادل مالی).

## تغییرات

- **NEW — فرم آپلود اکسل در تب «تخصیص»** (`render_assignment_panel`، هر دو دپارتمان رفع ناقصی/مالی):
  فایل XLSX/CSV شامل ستون CustomerID + دراپ‌داون «کارشناس مقصد» (تک‌انتخابی)؛ nonce مستقل
  `bcrm_deficiency_assign_upload`؛ پس از ثبت، به همان تب با پیام نتیجه برمی‌گردد.
- **NEW — سرویس `assign_from_customer_file(path, file_name, agent_id, department)`**
  (`includes/class-biawin-crm-deficiency-workflow.php`):
  1. فایل با همان موتور آپلود عودت/استفاده‌شده تجزیه می‌شود (`Biawin_CRM_Refund_Register::parse_upload`).
  2. Customer ID از هر هدر متعارف (`CustomerID`/`customer_id`/`شناسه مشتری`/…) یا اولین ستون پر برداشته می‌شود؛
     تکراری‌ها dedupe و شمرده می‌شوند؛ سقف ۱۰٬۰۰۰ شناسه یکتا.
  3. مشتری: `source_customer_id` → شناسه داخلی (id) → `customer_code`.
  4. همه پرونده‌های باز مشتری (`status NOT IN ('COMPLETED','ARCHIVED')`) به کارشناس واحد تخصیص می‌یابد
     (`assign_cases` تراکنشی با `FOR UPDATE` و رویداد timeline).
  5. گزارش کامل: assigned / duplicate_ids / not_found (شناسه ناشناخته) / no_case (بدون پرونده باز) —
     هر مورد با شماره سطر اکسل؛ لاگ ممیزی per-customer (`case_assigned_from_excel`) و per-batch
     (`case_assignment_excel_batch`).
- **هندلر `handle_deficiency_assign_upload`** (`admin/class-biawin-crm-admin.php`): همان گیت دسترسی
  تخصیص (`assign_finance_cases`/`assign_deficiency_cases` یا `bcrm_manage_cases`)، nonce مستقل،
  اعتبارسنجی `is_uploaded_file` + پسوند xlsx/csv، redirect به tab=assign با bcrm_message/bcrm_error،
  جزئیات per-ID در transient `bcrm_assign_upload_result_{user}`.
- هیچ تغییری در schema، مهاجرت‌ها یا فرمول‌های مانده/عودت — ارتقا code-only.

## قرارداد ورودی فایل

- ستون `CustomerID` (با هر مستعار شناخته‌شده) — ستون‌های دیگر نادیده گرفته می‌شوند.
- اگر ستون شناخته‌شده‌ای نبود، اولین سلول پرِ هر ردیف ملاک است (سازگاری با فایل‌های تک‌ستونی).
- ردیف کاملاً خالی skip می‌شود؛ شناسه‌های تکراری فقط یک‌بار پردازش می‌شوند.

## نتایج تست

| سوییت | نتیجه |
|---|---|
| `receipt-upload-runtime-v08600.php` (+TEST 25: ۱۷ ادعای جدید) | **406/0** |
| `refund-regression.php` (SQLite اجرایی) | **141/0** |
| `deficiency-supervisor-assignment-ui-v08593.php` (+۳ ادعای 62.15، پین 0.8.62.15) | **23/0** |
| `deficiency-portal-workflow-fixes-v08594.php` (پین 0.8.62.15) | **28/0** |
| `deficiency-finance-upload-handoff-v08598.php` (پین 0.8.62.15) | **23/0** |
| `deficiency-finance-roundtrip-v08599.php` (پین 0.8.62.15) | **20/0** |
| `php -l` روی کل ۴۸ فایل | تمیز |

## نسخه و بسته

- Header + `BIAWIN_CRM_VERSION` → **0.8.62.15**؛ `readme.txt` Stable tag + بلاک changelog؛ `CHANGELOG.md`.
- تأیید: unzip → `Version: 0.8.62.15` در هدر فایل اصلی + `assign_from_customer_file` و
  `admin_post_bcrm_deficiency_assign_upload` داخل ZIP.
