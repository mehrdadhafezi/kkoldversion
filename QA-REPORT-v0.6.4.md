# QA Report — v0.6.4

## Static checks
- All PHP files: `php -l`
- Admin JavaScript: `node --check`
- Plugin version and DB version bump to 0.6.4
- AJAX actions are registered for inspect, stage start, stage batches, commit start, commit batches and cancel.
- Legacy monolithic dry-run transient is cleared on the import page and is no longer used by the JavaScript flow.

## Import design checks
- Header/sample inspection does not materialize all worksheet rows.
- Full row data is never stored in a WordPress transient.
- Staging batch size: 500 rows.
- Commit batch size: 100 invoices.
- Invoice aggregation survives batch boundaries through staging upserts.
- Custom Excel columns remain supported via `ذخیره با همین سرستون`.
- Required invoice-code mapping remains enforced server-side and client-side.
- High-risk phone detection uses a grouped SQL query after staging.
- Final customer matching checks existing invoice and non-high-risk phone ownership.

## Fixture reviewed
The supplied `پورتال مشترکین.xlsx` contains about 24,056 rows and 23 columns. The redesigned path is intended specifically to avoid loading that whole sheet into PHP memory as nested arrays.

## Runtime limitation
This environment does not contain a WordPress runtime or PHP ZipArchive extension, so an end-to-end WordPress/XLSX execution could not be performed here. Install on staging/local WordPress and verify with the supplied workbook before production.
