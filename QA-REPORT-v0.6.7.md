# QA Report v0.6.7

## Source Excel validation
File checked: `پورتال مشترکین(1).xlsx`

- Data rows: 24,055
- Columns: 23
- Unique invoice codes: 24,055
- CustomerID values present on every data row: yes
- Unique CustomerID values physically present in file: 11,426
- Maximum numeric CustomerID: 11,803
- Missing CustomerID values between 1 and 11,803: 377
- `تایید پیش واریزی = تایید نشده`: 301 invoices

The importer now shows unique CustomerID count, maximum CustomerID and missing-ID count separately. A checked import option can create source-only placeholder profiles for missing numeric CustomerIDs so source profile range reaches 11,803. These placeholder profiles contain no invoice/name/phone because those rows do not exist in the uploaded file.

## Implemented changes
- Added mapping field `prepayment_approval` with aliases for `تایید پیش واریزی`.
- Invoice stores `prepayment_approval_status` and `prepayment_unapproved`.
- Customer portal and WP admin invoice lists show `پیش‌واریزی تأیید نشده` badge/tag.
- Assignment screen supports profile count limit, min/max total paid, one or multiple selected agents, or all active call-center agents.
- Multiple selected agents receive profiles round-robin.
- Assignment can operate on manually selected rows or filtered automatic selection.
- Added requested default services and dedicated service units.
- Created default supervisor logins for each default service cartable with initial password `[رمز پیش‌فرض قدیمی — از v0.8.63.0 حذف شد؛ این حساب‌ها دیگر خودکار ساخته نمی‌شوند]` only if the user does not already exist; upgrades never reset an existing password.
- Legacy seeded services are deactivated, not deleted, preserving historical service cases.

## Static verification
- All PHP files: `php -l` passed.
- `assets/admin.js`: `node --check` passed.
- `assets/frontend.js`: `node --check` passed.
