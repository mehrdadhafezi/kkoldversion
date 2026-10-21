# QA Report — v0.8.62.28

**تاریخ:** 2026-09-29
**موضوع:** بررسی فیش مالی در همان پنجره نمایش فیش (الگوی پنل مالی sales-network-v4 نسخه 2.0.117)

## مرجع

در sales-network-v4 (`public-manager.js` → `openReceipt`) دکمه «مشاهده فیش» مودالی باز می‌کند با دو ستون:
اطلاعات فاکتور/واریز + سوابق + «تأیید این واریز» / «رد این واریز» (دلیل inline) / «بازگشت به بررسی»، و
تصویر یا PDF فیش. عملیات AJAX است و فهرست بدون رفرش به‌روز می‌شود.

## پیاده‌سازی در CRM

| بخش | جزئیات |
|---|---|
| کلاس | `includes/class-biawin-crm-finance-review.php` (`Biawin_CRM_Finance_Review`) |
| جدول | `{prefix}bcrm_receipt_reviews` — attachment_id (PK)، case_id، review_status، approved_amount، review_note، reviewed_by، reviewed_at؛ ساخت خودکار در اولین استفاده |
| AJAX | `bcrm_fin_case_review` (داده پنجره)، `bcrm_fin_receipt_review` (approve/reject/reset)، `bcrm_fin_case_decide` (نتیجه نهایی → `process_action('finance_submit')`) |
| امنیت | ورود + nonce `bcrm_fin_review` + `can_access_case` + capabilityهای مالی؛ بررسی فقط در FINANCE_REVIEW_PENDING/ASSIGNED/UNDER_REVIEW؛ فیش باید متعلق به همان پرونده باشد |
| Timeline | `RECEIPT_APPROVED`، `RECEIPT_REJECTED`، `RECEIPT_REVIEW_RESET` با لیبل فارسی؛ خلاصه `receipt_reviews` در نتیجه مالی |
| UI | `portal-fixes.js` (مودال `.bcrm-frv`) + `portal-fixes.css` (روشن/تیره/موبایل)؛ نشان وضعیت روی هر کارت فیش؛ دکمه در صف مالی |

## تست‌ها

- `receipt-upload-runtime-v08600.php`: **609 passed, 0 failed** (TEST 31: payload، تأیید با مبلغ فیش و مبلغ ویرایش‌شده با ارقام فارسی، رد بدون دلیل ممنوع، بازبررسی بدون ردیف تکراری، reset، فیش پرونده دیگر، اقدام نامعتبر، Timeline، نتیجه نهایی از مسیر مشترک + خلاصه بررسی‌ها، فقط‌خواندنی پس از برگشت، قراردادهای UI/nonce/boot).
- سایر سوییت‌ها: بدون رگرسیون؛ pin نسخه چهار سوییت به 0.8.62.28 به‌روز شد. خطاهای باقیمانده (v08597، v08592، v08595، b21، b22، foundation، b23، v08591) ادعاهای قدیمی هستند که پیش از این تغییر هم fail بودند.
- مرورگر (Chromium، سرور mock با همین قرارداد JSON): ۴ فیش (PNG، PNG، PDF، HEIC) — تأیید → پرش خودکار به فیش بررسی‌نشده بعدی و به‌روزشدن نشان کارت؛ رد بدون دلیل → پیام خطا؛ PDF داخل صفحه؛ HEIC → کارت «باز کردن/دانلود»؛ مبلغ پیشنهادی نتیجه نهایی = جمع تأییدشده‌ها (1,600,000)؛ فقط فیلدهای نتیجه انتخابی ارسال شد؛ پس از ثبت، پنجره فقط‌خواندنی؛ نمای 1440px و 375px.
- `php -l` همه فایل‌ها، `node --check` هر دو JS.
