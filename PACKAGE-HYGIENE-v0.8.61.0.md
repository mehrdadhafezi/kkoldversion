# Package Hygiene — v0.8.61.0

- Sources: pristine v0.8.59.9 ZIP → extracted → v0.8.60.0 → v0.8.60.1 → v0.8.61.0 changes.
- Included: production PHP includes/admin, 3 JS assets, 2 CSS assets, readme.txt,
  CHANGELOG.md, QA + hygiene reports for the current version, plugin bootstrap.
- Excluded from the ZIP: `tests/` (including the runtime harness and wp-admin stubs),
  previous ZIPs, `.git`, `node_modules`, IDE files, OS junk, any `fake-abspath` stub tree.
- Verified: exactly 2 new production files (`class-biawin-crm-receipt-staging.php`,
  `QA-REPORT-v0.8.61.0.md`, `PACKAGE-HYGIENE-v0.8.61.0.md`) added since 0.8.60.1; no
  stray directories in the tree; ZIP root contains `biawin-crm-integration.php` with
  `Version: 0.8.61.0`.
- Lint: `php -l` clean on all production PHP (local ini warnings for missing xml DLLs
  are environmental noise, not lint findings); `node --check` clean on all JS assets.
