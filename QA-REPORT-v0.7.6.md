# QA Report v0.7.6

Static checks performed:
- All PHP files passed `php -l`.
- `assets/frontend.js` and `assets/admin.js` passed `node --check`.
- New Excel export action is registered and protected by `bcrm_reports` + nonce.
- Agent dashboard/customer list use the shared customer access scope.
- Customer list no longer executes one phone query per displayed row.
- XLSX exporter writes worksheet rows to disk and reads DB data in batches of 500.
- Upgrade adds performance indexes through `ensure_index`.

Runtime verification still required on the target WordPress/MySQL environment, especially EXPLAIN/query timing with production-size data and concurrent users.
