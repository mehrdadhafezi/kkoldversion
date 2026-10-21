# QA Report — Biawin CRM v0.8.62.2

## دامنه: معماری فقط-ادمین برای آپلود اکسل عودت — بدون هیچ تغییر منطقی

| بررسی | نتیجه |
|---|---|
| منوی ادمین واقعی وردپرس: `CRM بیاوین → ثبت عودت‌ها` | ✅ `add_submenu_page('biawin-crm', ..., 'bcrm_refund_register', 'biawin-crm-refund-register', ...)` |
| URL کانونی | ✅ `/wp-admin/admin.php?page=biawin-crm-refund-register` (صفحه + هندلر هر دو اینجا redirect می‌شوند) |
| محافظت با cap اختصاصی | ✅ هم رندر صفحه هم هندلر آپلود دوباره `current_user_can('bcrm_refund_register') \|\| bcrm_manage_cases` را چک می‌کنند |
| Administrator دسترسی کامل | ✅ Activator همه caps نقش portal_admin (شامل این cap) را به administrator می‌دهد |
| پیش‌فرض agent عادی | ✅ نقش `bcrm_agent` فقط `read + bcrm_work_cases` دارد؛ cap در آن نیست |
| کارشناس با تخصیص صریح | ✅ نقش آماده «ثبت‌کننده عودت اکسلی CRM» یا add_cap دستی → منو دیده می‌شود |
| حذف Upload از پرتال | ✅ ورود `refund-register` از `portal_views()` و map رندر حذف شد (۰ ارجاع باقی‌مانده) |
| تب «عودتی‌ها» حفظ شد | ✅ (`refunded` با همان cap، مشاهده/فیلتر) |
| دسترسی پروفایل عودتی حفظ شد | ✅ (`can_access_customer` شاخه bcrm_refund_register) |
| گزینه «عودت شده است» حفظ شد | ✅ default outcome + REST hook → REFUND_REPORTED دست‌نخورده |
| استقلال دو Flow | ✅ آپلود/Import فقط از کلاس Admin؛ پرتال هیچ `import_batch`ای صدا نمی‌زند |
| بدون تغییر منطق Refund/Tag/Balance/Import | ✅ فقط navigation؛ منطق دست نخورد |

## اجرای واقعی
```
php -l همه فایل‌ها: CLEAN
هارنس: TOTAL 259 passed, 0 failed (Test 18 جدید: ۱۱ ادعای گارد معماری)
Release blocker 20 فایل یکتا: PASS | roundtrip 20/0 | handoff 23/0 | workflow-fixes 28/0
```
