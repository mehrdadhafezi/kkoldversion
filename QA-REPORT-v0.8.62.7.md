# QA Report — Biawin CRM v0.8.62.7

**Date:** 2026-09-24 · **Base:** 0.8.62.6 (ZIP verified) · **Scope:** Excel-like per-column AutoFilter in the portal «عودتی‌ها» tab + numeric quick-search in amounts.

## Changes
1. **فیلتر ستونی اکسلی** (`includes/class-biawin-crm-frontend.php`, `render_refunded_list()`):
   - `f_balance` = `neg|pos|''` (مانده منفی/مثبت/همه) — select با submit خودکار.
   - `f_inv_min` / `f_inv_max` = بازه «مجموع فاکتورها» — ورودی متنی numeric، اعمال با دکمه.
   - Parsing با `isset` + `is_string` + allowlist/`is_numeric` — بدون Warning حتی با ورودی آرایه‌ای (WP_DEBUG محصول).
   - فیلترها **بعد از** precompute مانده و **قبل از** KPI/صفحه‌بندی اعمال می‌شوند → کارت‌های KPI (شامل «مانده منفی»)، صفحه‌بندی و شمارنده «نمایش X تا Y از N» همگی روی مجموعه فیلترشده هستند.
   - حفظ پارامترها در sort headers، صفحات قبلی/بعدی و فرم «در صفحه» (refund_tag/r_sort/r_dir/r_q/f_balance/f_inv_min/f_inv_max).
   - «حذف همه فیلترها» و لینک reset نوار بالا همه پارامترهای فیلتر/جست‌وجو را پاک می‌کنند.
   - ردیف خالی: پیام تمایز (فیلترها نتایج را خالی کرده‌اند vs. اصلاً رکوردی نیست) + لینک حذف فیلترها.
2. **جست‌وجوی عددی در مبالغ:** `r_q` عددی → match داخل «مجموع فاکتورها» و «مانده» هم.
3. **CSS** (`assets/portal-fixes.css`): `.bcrm-rf-filters` + حالت تاریک.

## Invariants kept
- منطق Last-Refund (`balance = مجموع فاکتورها − آخرین عودت اکسل`) دست نخورده.
- الگوی isset مربوط به r_pp (فیکس 0.8.62.6) حفظ شد؛ Test 20 همچنان پاس است.
- هیچ تغییر در آپلود اکسل، تگ‌ها، دسترسی‌ها و migration ها.

## Test results
- هارنس اصلی `tests/receipt-upload-runtime-v08600.php`: **310 passed / 0 failed** (Test 22 جدید: ۱۳ ادعا).
- `deficiency-finance-roundtrip-v08599.php`: 20/0 · `deficiency-finance-upload-handoff-v08598.php`: 23/0 · `deficiency-portal-workflow-fixes-v08594.php`: 28/0 — هر سه به 0.8.62.7 پین شدند.
- `php -l` روی همه فایل‌های PHP و `node --check` روی همه JS: CLEAN (هشدارهای startup دی‌ال‌های XML مربوط به محیط ویندوزی است و بی‌ربط).

## Versioning
- Header + `BIAWIN_CRM_VERSION` → 0.8.62.7؛ `readme.txt` Stable tag + ورودی changelog؛ `CHANGELOG.md` ورودی جدید.
- ZIP: `biawin-crm-integration-v0.8.62.7.zip` (پوشه پایدار `biawin-crm-integration/`).
