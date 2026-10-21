# Upgrade Notes v0.6.7

1. Back up the WordPress database before upgrade.
2. Install/replace plugin with v0.6.7.
3. Plugin migration adds invoice columns for prepayment approval.
4. Re-import the Excel file and map:
   - CustomerID -> Customer ID / شناسه مشتری CRM
   - تایید پیش واریزی -> تأیید پیش‌واریزی
5. Keep `تکمیل بازه CustomerID` checked only if CustomerID is an intended sequential range and source-only placeholder profiles are desired for missing IDs.
6. Default service supervisors use password `[رمز پیش‌فرض قدیمی — از v0.8.63.0 حذف شد؛ این حساب‌ها دیگر خودکار ساخته نمی‌شوند]` on first creation. Change these passwords after first login.
