# QA Report — v0.8.61.2 (staged sideload by-reference fatal fix)

## Incident (REAL host debug log, captured 2026-09-21)
Every staged receipt upload fataled with HTTP 500:
`PHP Fatal error: Uncaught Error: wp_handle_sideload(): Argument #1 ($file) cannot be passed by
reference in .../class-biawin-crm-receipt-staging.php:119 (0.8.61.0) / :167 (0.8.61.1)`.

## Root cause (the ACTUAL one — the earlier nonce theory was wrong for this incident)
`wp_handle_sideload()` is a WordPress core function whose first argument is declared BY REFERENCE
(it mutates the file entry with the stored path). `stage_entry()` passed a literal array directly,
which PHP 8 rejects with a fatal error — on EVERY file, always. The local harness shim declared
`function wp_handle_sideload($file, ...)` (by value), so the bug never surfaced in tests. The
nonce/cache hardening shipped in 0.8.61.1 remains valuable defense-in-depth but was NOT this bug.

## Fixes
1. `stage_entry()`: the sideload entry is assigned to `$sideload_file` first, then passed.
2. Harness shim signature corrected to `function wp_handle_sideload(&$file, ...)` so the real
   WordPress contract is enforced in every future test run.

## Verification (real execution)
- Suite: 191 passed / 0 failed.
- Release blocker: 20 unique files → staged 20 / persisted 20 / unique 20 / duplicates 0 / missing 0.
- 28-file staged batch, sparse keys, delete-middle, same-name-different-content, exact-duplicate,
  rollback, finance round-trip: all PASS.
- Legacy suites unchanged (remaining failures are pre-existing stale pins, verified on pristine).

## Version
0.8.61.2 — code-only upgrade from any of 0.8.58.0…0.8.61.1; no schema changes.

## Fixes (production)
1. `Biawin_CRM_Receipt_Staging::authorize()` — layered auth: valid nonce + logged-in ⇒ allow;
   logged-in + matching site token (hash_equals) ⇒ allow with `stage_auth_token_fallback` log;
   otherwise explicit 401 (guest, re-login guidance) / 403 with `stage_auth_rejected` diagnostics
   (logged_in, nonce_ok, token_ok, Content-Type).
2. `bcrm_stage_config` AJAX endpoint (priv + nopriv, `nocache_headers`) returning fresh nonce + token.
3. JS (portal-fixes.js + frontend.js): automatic config refresh + retry on 401/403/code=auth;
   per-file retry ×3 with 400/800ms backoff on transient failures; alert shows the FIRST server
   reason verbatim (file name + message); real progress text; `window.BCRM_STAGE_CFG` cache.
4. Nopriv stage requests get structured JSON + real status codes instead of WP's bodyless `0` die.
5. Diagnostics: `stage_request_received`, `stage_no_payload`, richer `stage_sideload_failed`.
6. JS error transparency (added after the 0.8.61.0 incident report): every failed stage request now
   surfaces the exact HTTP status + a text snippet of non-JSON server replies (WAF block pages), and
   a 403-without-JSON explicitly names mod_security/host firewall so hosting support can act.
   `status===0` (network drop) gets its own distinct message instead of the old generic one.

## Results (real runtime execution — receipt-upload-runtime-v08600.php)
- TOTAL: 191 passed, 0 failed.
- Test 14 (new): stale nonce + valid token + logged-in → 18/18 staged AND 18/18 persisted (the exact
  incident signature); wrong token → 403; guest → 401 with guidance; config endpoint returns fresh
  nonce + token; valid nonce path unchanged.
- Release blocker (20 unique files) PASS: selected 20 / received 20 / uploaded 20 / persisted 20 /
  unique 20 / duplicates 0 / missing 0.
- 28-file staged batch beyond any server cap: 28/28/28/28, duplicates 0, missing 0 (Test 13).
- Guards unchanged: unknown id, id/amount mismatch, mid-batch failure (full rollback), repeated id
  (persisted once, duplicate_count=1).
- Legacy suites: v08594/v08598/v08599 green after version-pin updates; remaining failures in
  v08597-era suites are pre-existing stale pins verified identical on the pristine v0.8.59.9 package.
- `php -l` clean on all PHP; `node --check` clean on portal-fixes.js / frontend.js / admin.js.

## Ops notes for the hosting side (still recommended)
- Purge LiteSpeed/CDN cache after deploying so browsers fetch fresh JS + config.
- Keep `max_file_uploads ≥ 100`, `upload_max_filesize=64M`, `post_max_size=128M` via panel/.user.ini
  (helps other flows; the receipt workflow no longer depends on it).
- If staging still fails at a host, check the plugin log for `stage_no_payload` / `stage_auth_rejected`
  / `stage_sideload_failed` — the Content-Type and error fields point at mod_security/POST filters.

## Version
0.8.61.1 — code-only upgrade from any of 0.8.58.0…0.8.61.0; no schema changes.
