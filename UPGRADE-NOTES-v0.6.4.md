# Biawin CRM v0.6.4 — Streaming/Batch Import

## Why this release exists
The previous importer still materialized the complete parsed dataset during dry-run/analyze and stored a very large analysis object in a WordPress transient. With 24k+ rows this could exhaust a 256 MB PHP memory limit, often surfacing inside `wpdb` while serializing/writing the oversized transient.

## New architecture
1. Upload is persisted in a protected temporary uploads directory.
2. Header inspection is bounded-memory and only materializes header/sample rows.
3. XLSX/CSV is normalized to a temporary CSV using streaming row reads; the worksheet is never materialized as one PHP array.
4. Staging runs over AJAX in batches of 500 rows.
5. Invoice aggregates are persisted in staging tables so rows belonging to one invoice can cross batch boundaries safely.
6. Phone-to-invoice links are staged separately; high-risk phone detection is computed in SQL.
7. Preview contains only counters, not every invoice/group payload.
8. Final commit runs over AJAX in batches of 100 invoices.
9. Temporary source/normalized files are removed after successful completion or explicit cancellation.

## Database additions
- `wp_bcrm_import_jobs`
- `wp_bcrm_import_stage_invoices`
- `wp_bcrm_import_stage_phones`

`wp_` follows the active WordPress table prefix.

## Upgrade
Installing/activating 0.6.4 runs the normal versioned schema upgrade and creates the staging tables.
