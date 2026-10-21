# QA Report v0.8.60.1

## Scope

Follow-up to the v0.8.60.0 receipt-upload data-integrity fix. Production reported the
**expected** guard behavior: 28 receipt rows were declared but only 20 files reached the
server, so the request was rejected as incomplete ("درخواست ناقص است و پردازش متوقف شد").
Root cause of the truncation: the hosting server's PHP `max_file_uploads` cap (default 20)
silently drops file slots beyond the cap BEFORE the plugin sees them. No data was corrupted
or partially persisted — v0.8.60.0 did its job. This release adds prevention + guidance.

## Changes

1. **Exact-cap signature detection** — `Biawin_CRM_Upload_Normalizer::looks_like_max_uploads_cap()`:
   a request with `received === max_file_uploads` while more rows were declared is identified
   as the hosting cap. PHP itself enforces the cap, so `received > max` can never occur on a
   truncated request; `received == max` with under-declared counts is the only observable
   signature.
2. **Actionable error** — the truncation error now names the cause and fix in Persian
   ("سرور هاستینگ شما در هر درخواست حداکثر 20 فایل می‌پذیرد …") and appends
   `Biawin_CRM_Upload_Normalizer::server_limit_notice()` with step-by-step hosting-panel and
   `.user.ini` instructions plus the current ini values.
3. **Pre-submit browser guard** — the receipt form counts chosen files and:
   - refuses to add rows beyond the cap (with an explanatory `alert`),
   - disables the add-row button at the cap (`.is-cap-disabled`),
   - blocks submission when chosen files exceed the cap, explaining the multi-batch workaround.
   Guards read the cap from `maxFileUploads` localized into both `BiawinCRM` and `BCRM_ADMIN`
   (`Biawin_CRM_Upload_Normalizer::max_uploads_ini()`); no guessing, no hardcoded 20.
4. **Visible hint** — the receipt form shows the server's current simultaneous-file cap
   (`data-bcrm-receipt-limit` / `receipt_limit_hint()`) before any rows are added.

## Real runtime tests (`tests/receipt-upload-runtime-v08600.php`)

120 assertions / 0 failures on real file uploads through the production pipeline, including:

- TEST 1 (release blocker): 20 unique files → 20 received / 20 uploaded / 20 persisted /
  20 unique / 0 duplicates / 0 missing — **PASS**
- TEST 9b (new): 28 declared → 20 received on a 20-slot build → rejected end-to-end, error
  names the hosting cap and includes the fix guidance, 0 DB inserts — **PASS**
- TEST 9c (new): both JS assets read the localized cap and block over-cap submission — **PASS**
- Sparse keys, delete-middle, same-name/different-content, exact-duplicate rejection,
  rollback, round-trip (RETURNED_TO_DEFICIENCY → resend, 5 historical attachments) — **PASS**

## String suites

Updated the version pins that intentionally track the release (v08594/v08598/v08599): all
green. The v0.8.59.7-era suites (v08592/v08593/v08595/v08597, b21/b22/b23,
reporting-foundation) still fail on pre-existing stale version pins — verified earlier to
fail identically on the pristine v0.8.59.9 package, unrelated to this fix.

## Quality gates

- `php -l` clean on all production + test PHP files.
- `node --check` clean on `frontend.js`, `portal-fixes.js`, `admin.js`.

## Deliverable

- ZIP: `biawin-crm-integration-v0.8.60.1-receipt-upload-host-cap-guard.zip`
- Version: 0.8.60.1 (header, `BIAWIN_CRM_VERSION`, activator chain …0.8.60.0→0.8.60.1, readme stable tag, CHANGELOG)
