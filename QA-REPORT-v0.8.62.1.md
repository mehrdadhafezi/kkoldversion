# QA Report — Biawin CRM v0.8.62.1

## دامنه: فقط فیکس‌های تأییدشده audit — هیچ feature جدیدی

| # | Fix | فایل | رفتار |
|---|---|---|---|
| 1 | فیلترهای عودت از HTTP | `refund-register.php` (helper) + `admin` (GET/POST) + `frontend` (tab) | ورودی هر اندامسی → helper قبل از allowlist uppercase می‌کند؛ دیگر به `any` برنمی‌گردد |
| 2 | عودت اعلامی در تب عودت‌ها | `refund-register.php::apply_reported_tag` + `frontend.php` (رندر) | رکورد `source_type='call'` بدون مبلغ (مانده ثابت)، actor_name + created_at؛ منبع: «اعلام‌شده توسط کارشناس»؛ ذخیره مجدد همان interaction رکورد تکراری نمی‌سازد |
| 3 | نقش در کاربران پرتال | `auth.php::role_labels()` | `bcrm_refund_register` → «ثبت‌کننده عودت اکسلی» |

## صریحاً دست‌نخورده (طبق دستور)
- معنای 0: همان Skip با گزارش ردیف (تغییری نکرد)
- نام تگ‌ها: `REFUND_REPORTED` / `REFUND_CONFIRMED`
- فرمول مانده: کل فاکتورها − آخرین عودت قطعی اکسل (تأیید مجدد با تست: 175M)

## اجرای واقعی
```
php -l همه فایل‌ها: CLEAN | node --check هر ۳ JS: CLEAN
هارنس: TOTAL 248 passed, 0 failed
  Test 17 جدید: ۲۲ ادعا (ماتریس فیلتر ۱۰حالته، resolve end-to-end،
  رکورد call با actor=42 و batch=call-777، idempotency، مانده 50M ثابت، لیبل نقش)
Release blocker 20/20/20/20: PASS
roundtrip 20/0 | handoff 23/0 | workflow-fixes 28/0 (پین‌ها به 0.8.62.1 ارتقا یافت)
```
