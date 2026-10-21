# QA Report — Biawin CRM v0.8.61.4

## هدف این نسخه
تحلیل لاگ ۱۷:۳۳ سرور (۱۳× `receipt_staged_duplicate` برای همان id) نشان داد یک مسدودکننده انتشار باقی است: معناشناسی «رد کردن تکراری‌ها» ردیف‌های هم‌محتوا را حذف می‌کرد. این نسخه معناشناسی را به **ثبت ردیف‌به‌ردیف** تغییر می‌دهد و یک باگ لایه‌مدیای واقعی را که فقط در وردپرس تولیدی نمایان می‌شد رفع می‌کند.

## ریشه واقعی (Two-part)

### 1) Skip-repeat dedup حذف ردیف‌ها و مبالغ
`finalize_for_case()` هر id تکراری را `continue` می‌کرد → فقط **یک** ضمیمه با مبلغ ردیف اول ساخته می‌شد؛ `staged_receipt_amounts` کامل می‌آمد ولی ردیف‌های تکرارشده هرگز مصرف نمی‌شدند → مبالغ بی‌صدا گم می‌شدند و پرونده گیر می‌کرد. مسیر inline همان شکل را داشت (`duplicate_receipt_rejected` → `continue`).

### 2) media_handle_upload فایل روی دیسک را رد می‌کند (کشف واقعی وردپرس)
finalize فایل مرحله‌گذاری‌شده را با پر کردن `$_FILES` به `media_handle_upload()` می‌داد. اما `media_handle_upload()` واقعی وردپرس روی `tmp_name` **`is_uploaded_file()`** اجرا می‌کند؛ یک فایل صف‌شده در staging هرگز «آپلود مدیریت‌شده PHP» نیست → **هر ردیف مرحله‌گذاری‌شده در وردپرس تولیدی شکست می‌خورد**. شیم هارنس `media_handle_upload` فایل را کورکورانه کپی می‌کرد و این را ماسک کرده بود.

## تغییرات (normalized algorithm)

| ناحیه | قبل | بعد (v0.8.61.4) |
|---|---|---|
| finalize تکرار | `continue`؛ skip؛ مبلغ گم | هر ردیف ثبت؛ مبلغ ردیف حفظ؛ `duplicate_count` فقط گزارشی (`finalize_repeated_content_row`) |
| منبع ردیف | مسیر staged مشترک | کپی موقت برای هر ردیف (`wp_tempnam`) — منبع پاک؛ نسخه staged تا consume() می‌ماند؛ temp بعد از هر ردیف unlink |
| مسیر مدیا | `media_handle_upload()` برای همه | staged → `media_handle_sideload()` (مسیر بومی دیسک) + `wp_check_filetype_and_ext` پیش از ورود؛ inline → `media_handle_upload()` |
| تشخیص staged | — | `$field === 'bcrm_case_document_upload' \|\| !is_uploaded_file($candidate['tmp_name'])` (هر دو مسیر inline و legacy single-file امن) |
| inline تکرار | reject+continue | ثبت ردیف با مبلغ خودش (`duplicate_receipt_row_content_repeat`)؛ بخش «همه تکراری» اکنون غیرقابل‌دسترس است (حذف منطقی) |
| rollback | بدون تغییر | کل درخواست در هر خرابی ردیف برمی‌گردد (DB rows + media)؛ ضمیمه‌های راند قبلی دست‌نخورده |

## Harness hardening (تا این کلاس باگ دوباره مخفی نشود)
- شیم `media_handle_upload`: اکنون **`is_uploaded_file()` را اعمال می‌کند** (امضای واقعی) → خوراندن فایل staged به این مسیر در تست هم فتال/فیل می‌شود.
- شیم `media_handle_sideload` (by-reference، mime-check، copy-into-uploads) و شیم `wp_tempnam` اضافه شد.
- تست‌های تکراری (Test 5 / 5b / 13b-d) به انتظارات ردیف‌به‌ردیف بازنویسی شدند؛ شامل حفظ مبلغ ردیف دوم در note.

## نتایج اجرا (اجرای واقعی، نه جست‌وجوی متن)

```
=== 20 UNIQUE FILE TEST (release blocker) ===
selected: 20 | received: 20 | uploaded: 20 | persisted: 20 | unique: 20 | duplicates: 0 | missing: 0
RESULT: PASS

TOTAL: 193 passed, 0 failed   (runtime harness, شامل دسته ۲۸ فایلی، sparse keys،
                               delete-middle، rollback، auth incident، Test 14)

deficiency-finance-roundtrip-v08599:      TOTAL_PASS=20  TOTAL_FAIL=0
deficiency-finance-upload-handoff-v08598: TOTAL_PASS=23  TOTAL_FAIL=0
deficiency-portal-workflow-fixes-v08594:  TOTAL_PASS=28  TOTAL_FAIL=0

php -l: همه فایل‌های PHP تمیز   |   node --check: هر ۳ asset تمیز
```

سوئیت‌های قدیمی با TOTAL_FAIL>0 **دقیقاً مثل پکیج خام v0.8.59.9** شکست می‌خورند (پین‌های ازقبل‌موجود — baseline-pristine comparison انجام شد؛ صفر رگرسیون جدید).

## رفتار قابل مشاهده پس از نصب
- همان فایل در چند ردیف: **هر ردیف یک ضمیمه با مبلغ خودش**؛ duplicate_count در لاگ فقط تکرار محتوا را گزارش می‌کند.
- لاگ سرور: به‌جای سکوت پس از `stage_request_received`، خط `receipt_staged_duplicate` ظاهر می‌شود (v0.8.61.3) و finalize همه ردیف‌ها را ثبت می‌کند.
- پرونده پس از ثبت کامل: FINANCE_REVIEW_PENDING.
