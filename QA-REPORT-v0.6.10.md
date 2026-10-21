# QA Report v0.6.10

Checks for the operations-user login migration:

- 27 seeded operations users are defined with normalized 11-digit Iranian mobile usernames beginning with 0.
- Legacy generated usernames are migrated in-place by WordPress user ID.
- Password reset is protected by one-time migration option `biawin_crm_ops_mobile_login_migrated_v0610`.
- Portal login normalizes 10-digit mobile, +98/98/0098 variants, Persian digits and Arabic digits.
- Roles remain supervisor for Kourosh Mizban and agent for the remaining 26 users.
