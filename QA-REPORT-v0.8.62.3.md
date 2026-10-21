# QA Report — Biawin CRM v0.8.62.3

## PART A — ریشه واقعی خطای «شما اجازهٔ دسترسی به این برگه را ندارید»

**زنجیره علت:**
1. صفحه `biawin-crm-refund-register` با cap اختصاصی `bcrm_refund_register` ثبت می‌شود (منوی ادمین، callback و هندلر آپلود — همه یک cap).
2. `bcrm_refund_register` فقط داخل `register_activation_hook` به administrator داده می‌شد.
3. نصب‌های موجود (0.8.61.x → 0.8.62.x ارتقا یافته) **هیچ‌وقت activation دوباره نداشتند** → نقش administrator در DB فاقد این cap ماند → `current_user_can('bcrm_refund_register')` = false → پیام «Sorry, you are not allowed to access this page».
4. (قبل از v0.8.62.0 صفحه اصلاً وجود نداشت؛ این خطا از همان اول فقط برای نصب‌های ارتقایی رخ می‌داد.)

**FIX (فقط-افزایشی، idempotent):**
- `ensure_roles_and_caps()`: administrator حالا **اتحاد** capهای همه نقش‌های تعریف‌شده را می‌گیرد — هر cap جدید آینده هم خودکار به administrator می‌رسد. هیچ cap حذف نمی‌شود، هیچ نقشی reset نمی‌شود.
- نشانگر `biawin_crm_caps_marker` = 3: اگر عقب‌تر بود، re-grant روی `admin_init` (هر بار بارگذاری ادمین).
- فراخوانی در bootstrap: مستقیم هنگام load پلاگین + `admin_init` — پوشش کامل نصب‌های موجود بدون deactivate/reinstall.

**امنیت (بدون تغییر):** Administrator ALLOW؛ دارنده cap ALLOW؛ `bcrm_agent`/`bcrm_deficiency_agent` DENY (cap ندارند)؛ دسترسی مستقیم URL بدون cap → `wp_die` وردپرس. هر سه لایه (منو، callback، هندلر POST/AJAX) همان یک cap.

## PART B — ارتقای استاندارد ZIP (بدون deactivate)

- **شناسه پلاگین:** پوشه داخل ZIP = `biawin-crm-integration/` (بدون شماره نسخه)، فایل اصلی `biawin-crm-integration.php`، Header Name = `Biawin CRM Integration` — WordPress آن را همان پلاگین نصب‌شده می‌شناسد و روی نسخه **فعال** «Replace current with uploaded» ارائه می‌دهد؛ پلاگین فعال می‌ماند (رفتار بومی Plugin_Upgrader؛ هیچ `deactivate_plugins`/حذف دایرکتوری در کد نیست).
- **مهاجرت ترتیبی:** `biawin_crm_db_version` (جدا از `BIAWIN_CRM_VERSION`)؛ جدول `MIGRATIONS` = ['0.8.62.0', '0.8.62.2'] به‌ترتیب اجرا می‌شوند؛ هر مهاجرت idempotent/غیرمخرب. قفل `bcrm_upgrade_lock` (timeout 300s) از اجرای هم‌زمان جلوگیری می‌کند؛ در شکست: لاگ `upgrade_migration_failed` (نسخه، خطا، timestamp) + نسخه DB جلو نمی‌رود → retry امن در بار بعدی. `upgrade_activation()` پس از مهاجرت‌ها schema را (idempotent) تضمین می‌کند.
- **حفظ داده:** هیچ DROP/DELETE/RESET در مسیر ارتقا نیست — مشتری‌ها، پرونده‌ها، تخصیص‌ها، ضمیمه‌ها، عودت‌ها، تگ‌ها، فاکتورها، گزینه‌ها، نقش‌ها و لاگ‌ها دست‌نخورده می‌مانند.

## نتایج اجرا (همین الان)

```
php -l همه فایل‌ها + node --check هر ۳ JS: CLEAN
هارنس اصلی: 276 passed, 0 failed
  Test 19 جدید (۱۷ ادعا): cap یکسان در menu/callback/handler، caps marker v3،
  اتحاد administrator، bcrm_agent/deficiency_agent بدون cap، MIGRATIONS ترتیبی + قفل،
  biawin_crm_db_version مستقل، ZIP با فقط یک پوشه ریشه biawin-crm-integration/
Release blocker آپلود (20 فایل یکتا): 20/20/20/20 → PASS
roundtrip: 20/0 | handoff: 23/0 | workflow-fixes: 28/0
بازرسی ZIP: 216 فایل، فقط biawin-crm-integration/ ریشه، بدون tests/موقت/.git
```

## پذیرش معیارها

| معیار | وضعیت |
|---|---|
| A — صفحه ادمین برای Administrator بدون خطای دسترسی | ✅ (cap grant خودکار) |
| B — Administrator دارای `bcrm_refund_register` | ✅ |
| C — agentهای عادی پیش‌فرض بدون cap | ✅ |
| D — نصب‌های موجود بدون reinstall cap می‌گیرند | ✅ (admin_init + marker) |
| E — ZIP روی نسخه فعال با Upload Plugin جایگزین می‌شود | ✅ (پوشه استاندارد) |
| F — پلاگین پس از ارتقا فعال می‌ماند | ✅ (رفتار بومی) |
| G/H — داده و عملکرد عودت دست‌نخورده | ✅ (migration غیرمخرب؛ سوئیت‌ها سبز) |
| I — مهاجرت idempotent/قفل‌دار/retry-safe | ✅ |
| J — ساختار ZIP استاندارد | ✅ |

*تست زنده نهایی (بازکردن صفحه ادمین و Upload Plugin روی سایت تولیدی) پس از نصب ZIP توسط کاربر انجام می‌شود؛ در سمت کد همه گاردها با هارنس runtime تأیید شد.*
