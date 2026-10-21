# QA Report v0.8.40

Focus: production-safe historical identity audit after the CustomerID ownership correction incident.

## Safety
- Historical audit is read-only against CRM domain tables.
- Ownership correction execute is blocked by default.
- Unapproved-invoice cleanup execute is blocked by default.
- Preview/reporting remain available.
- Controlled local/staging override: define `BIAWIN_CRM_MAINTENANCE_SAFETY_LOCK` as `false` in wp-config.php.

## Baseline
Bundled streaming baseline was generated from:
- `bcrm-backup-20260818-142835-manual.ndjson`
- `خروجی_نهایی_مشتریان_اصلاح_شده.xlsx`

It includes affected historical profiles and all profiles that were Complete in the healthy backup. The baseline is NDJSON and is processed in small batches to avoid loading the entire 39 MB dataset into PHP memory.

## Audit signals
- Deterministic vs ambiguous historical identity mappings.
- Historical Complete profile whose current destination is no longer Complete.
- Historical internal customer row reused for another CustomerID.
- Declared-paid values confirmed to be stranded on the reused historical row.
- Missing destinations and target-name mismatches.
- Historical operational record counts and safe forward-reconstruction candidates.

## Static checks
- PHP lint all plugin PHP files.
- JavaScript syntax check for admin.js and frontend.js.
- ZIP root/plugin bootstrap/version checks.
