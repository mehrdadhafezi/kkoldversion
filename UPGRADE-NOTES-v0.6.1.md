# Upgrade Notes — v0.6.1

This release is a bug-fix and persistence-hardening update for v0.6.0.

## Important
- Version bump to `0.6.1` intentionally forces `maybe_upgrade()` to run and repair the database schema.
- Existing CRM data is not intentionally deleted by the upgrade.
- Before production upgrade, keep a database backup and the previous plugin ZIP.

## If save still fails after upgrade
Go to:
`CRM بیاوین → تنظیمات CRM → سلامت دیتابیس افزونه`
and click:
`بررسی و ترمیم ساختار دیتابیس`

Then retest interaction save from the portal.
