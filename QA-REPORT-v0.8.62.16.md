# QA Report — v0.8.62.16

**تاریخ:** 2026-09-27
**محدوده:** تخصیص از فایل اکسل Customer ID در صفحه «تخصیص شماره موبایل» (پرتال `?bcrm_view=assignments` + صفحه ادمین `biawin-crm-assignments`).

## زمینه

نسخه 0.8.62.15 قابلیت آپلود اکسل را در تب «تخصیص» Workflow رفع ناقصی اضافه کرد؛ کاربر روشن کرد که
مقصود صفحه «تخصیص شماره موبایل» است (تخصیص پروفایل مشتری به کارشناس تماس). این نسخه همان قابلیت را
در آن صفحه پیاده می‌کند. قابلیت 0.8.62.15 (Workflow رفع ناقصی/مالی) دست‌نخورده باقی می‌ماند.

## تغییرات

- **کارت «تخصیص از فایل اکسل (Customer ID)»** در `assignments_page()` — بین «جستجو و تخصیص مستقیم» و
  «Rule مدیر فروش»: فایل XLSX/CSV + دراپ‌داون «کارشناس مقصد» (تک‌انتخابی از همان لیست کارشناسان تماس
  فعال صفحه) + nonce مستقل `bcrm_assign_customers_excel`.
- **سرویس `assign_profiles_from_customer_file(path, file_name, agent_id)`** در کلاس Admin:
  - تجزیه فایل با همان موتور (`Biawin_CRM_Refund_Register::parse_upload`)؛ Customer ID از هدر متعارف
    یا اولین ستون پر؛ dedupe تکراری‌ها؛ سقف ۱۰٬۰۰۰ شناسه.
  - تطبیق مشتری: `source_customer_id` → شناسه داخلی → `customer_code`.
  - قواعد عیناً مثل `handle_assignment()`: تراکنش + `FOR UPDATE` روی مشتری و تخصیص‌های فعال،
    اصلاح duplicate تخصیص فعال (`duplicate_closed`)، اگر تخصیص فعال وجود داشت به کارشناس جدید
    **منتقل** می‌شود (reassign؛ نه رکورد تکراری)، وگرنه insert؛ `customers.status='assigned'`.
  - لاگ `assigned_to_agent` با `source=excel` و شماره سطر اکسل + Activity `customer_assigned` +
    رویداد batch `assignment_excel_batch` (unique/duplicates/assigned/not_found).
- **هندلر `handle_assign_customers_excel`** (`admin_post_bcrm_assign_customers_excel`): گیت
  `bcrm_manage_cases` + nonce، اعتبارسنجی `is_uploaded_file` + پسوند، مسیر خطا به همان
  transient هشدار صفحه (`bcrm_assignment_error_`)، مسیر موفق: جزئیات (شناسه‌های ناشناخته با سطر
  اکسل) در همان transient «جزئیات آخرین تخصیص» که صفحه قبلاً نمایش می‌دهد + فیلد جدید `summary`
  (سازگار به عقب) + redirect با شمارنده `assigned`.
- هیچ تغییر schema/migration — ارتقا code-only.

## نتایج تست

| سوییت | نتیجه |
|---|---|
| `receipt-upload-runtime-v08600.php` (+TEST 26: ۲۵ ادعای جدید) | **431/0** |
| `refund-regression.php` (SQLite) | **141/0** |
| `deficiency-supervisor-assignment-ui-v08593.php` (+۶ ادعای 62.15/62.16، پین 0.8.62.16) | **26/0** |
| `deficiency-portal-workflow-fixes-v08594.php` (پین 0.8.62.16) | **28/0** |
| `deficiency-finance-upload-handoff-v08598.php` (پین 0.8.62.16) | **23/0** |
| `deficiency-finance-roundtrip-v08599.php` (پین 0.8.62.16) | **20/0** |
| `php -l` کل فایل‌ها | تمیز |

## نسخه و بسته

- Header + `BIAWIN_CRM_VERSION` → **0.8.62.16**؛ readme Stable tag + بلاک changelog؛ CHANGELOG.md.
- تأیید: unzip → `Version: 0.8.62.16` + `assign_profiles_from_customer_file` و
  `admin_post_bcrm_assign_customers_excel` داخل ZIP.
