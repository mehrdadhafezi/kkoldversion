# Biawin CRM v0.6.3

## Fixed
- XLSX header inspection no longer loads the whole worksheet XML into memory. Worksheet XML is extracted to a temp file and parsed in bounded chunks.
- XLSX full parse also avoids loading the entire worksheet XML string/matrix at once, reducing peak memory usage.
- Customer journey AJAX now reads the customer id from the profile root, not only from serialized interaction form data.
- AJAX customer-id errors now distinguish missing id, missing profile, and access denial.

## Import improvements
- Added `ذخیره با همین سرستون` mapping option for extra Excel columns.
- Standard CRM fields remain available and invoice code remains mandatory.
- Extra column values are stored on the invoice as `extra_json` and displayed in the customer profile under `اطلاعات تکمیلی اکسل`.
- Added schema migration for `bcrm_invoices.extra_json`.

## Upgrade
Install over v0.6.2. The plugin version bump triggers database migration automatically.
