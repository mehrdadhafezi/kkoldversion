# Upgrade notes v0.6.8

- Added `تاریخ اقدام فروشنده` as a first-class import mapping field.
- Each invoice stores seller action date and Jalali year.
- Customer `assignment_cohort=1404` only when every attached invoice has seller_action_year=1404. Any 1405/other/missing year keeps the customer in normal assignment.
- Added separate assignment tabs for normal and 1404 cohorts; automatic/manual allocation is constrained to the active tab.
- Added 27 default operations users from the supplied roster: کورش میزبان as CRM supervisor and 26 agents. Initial password for newly seeded users is [رمز حذف شد] and should be changed after first login.
- Added 1404 badge on customer profile.
