# Upgrade notes — v0.7.7

This release is a hardening release for v0.7.6. No destructive data migration is introduced.

Recommended procedure:
1. Back up database and current plugin directory.
2. Replace/update the plugin with v0.7.7.
3. Load the portal once as an administrator so normal `maybe_upgrade()` checks run.
4. Open CRM Settings and run the database structure repair/check once if this installation has passed through older 0.6.x versions.
5. Hard-refresh browser assets (Ctrl+F5).
6. Execute the staging acceptance flow in `QA-REPORT-v0.7.7.md`.
