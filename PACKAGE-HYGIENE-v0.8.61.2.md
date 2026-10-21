# Package Hygiene — v0.8.61.2

- Distribution root contains only production code + docs; `tests/`, `fake-abspath/`, OS junk,
  editor files, previous ZIPs and build scripts are excluded from the ZIP.
- New in 0.8.61.2: no new files; changes confined to
  `includes/class-biawin-crm-receipt-staging.php`, `assets/portal-fixes.js`, `assets/frontend.js`,
  `biawin-crm-integration.php` (version), `includes/class-biawin-crm-activator.php` (upgrade chain),
  `readme.txt`, `CHANGELOG.md`, this report, and `QA-REPORT-v0.8.61.2.md`.
- `wp-admin/includes` stubs are NOT shipped; the plugin requires them under ABSPATH at runtime only.
- ZIP verified: root `biawin-crm-integration.php`, header `Version: 0.8.61.2`, no test/temp entries.
