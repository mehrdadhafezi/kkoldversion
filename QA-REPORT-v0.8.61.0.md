# QA Report — v0.8.61.0 «Receipt staged uploads: unlimited count, unlimited rows, all image formats»

## Goal

Remove the receipt-upload count limit **by construction** (not by reporting it) and widen
the accepted formats, after the hosting incidents: 20 selected → ~15 persisted (v0.8.59.9
data corruption), then 28 selected → only 20 received (`max_file_uploads = 20` host cap).

## Architecture change (v0.8.61.0)

1. **Staging**: the browser uploads every selected receipt file individually — one file
   per AJAX request (`bcrm_stage_receipt`) — as part of submit. Each request carries a
   single file, so `max_file_uploads` and `post_max_size` can never truncate the batch.
2. **Storage**: staged payloads live in `wp_options` under a per-form token, keyed by
   SHA-256 content identity (dedup: same content staged twice returns one id). TTL 6h.
3. **Finalize**: the form submits `staged_receipt_ids[]` + `staged_receipt_amounts[]`.
   `send_to_finance()` validates ids↔amounts 1:1, materializes each staged file through
   the production media + DB path (`save_uploaded_document()`), then moves the case to
   `FINANCE_REVIEW_PENDING`. Success consumes the staging state and deletes staged files.
4. **Rollback**: unknown id / missing staged file / id-amount mismatch / any materialize
   failure → loud error + FULL rollback of this request's rows and media. Previous-round
   attachments are never deleted or overwritten. Repeated staged id = duplicate
   accounting, persisted once (mirrors the inline path's content-hash dedup).
5. **Formats**: `RECEIPT_MIMES` now covers every image type WordPress knows (JPG, JPEG,
   PNG, GIF, WebP, AVIF, HEIC, HEIF, BMP, TIFF, ICO) plus PDF — shared by the inline and
   staged paths. Per-file size cap stays 8 MB (validation, not a data-integrity limit).
6. **Legacy inline path unchanged in behavior**: canonical normalization, SHA-256 dedup,
   truncation detection and rollback still guard non-JS clients and API flows.

## Runtime verification (real execution, not string inspection)

Harness: `tests/receipt-upload-runtime-v08600.php` (real temp files, real SQLite
persistence, real media-file simulation, real staging state on disk).

### Release blockers

```
=== 20 UNIQUE FILE TEST (inline path, legacy clients) ===
selected: 20 | received: 20 | uploaded: 20 | persisted: 20 | unique: 20 | duplicates: 0 | missing: 0
RESULT: PASS

=== TEST 13: STAGED batch of 28 files (beyond every server cap) ===
staged files accepted (no count cap): 28
uploaded media files: 28
DB attachment inserts: 28
unique persisted identities: 28
duplicate_count: 0 | missing: 0
amount↔file pairing preserved row-by-row
case moved to FINANCE_REVIEW_PENDING
staging state consumed + staged temp files cleaned
RESULT: PASS
```

### Additional staged-path guarantees (TEST 13b)

- Unknown staged id → loud error, 0 persists. PASS
- id/amount count mismatch → rejected, 0 persists. PASS
- Mid-batch failure (staged file missing on disk) → whole submit rejected, 0 DB rows,
  already-materialized media rolled back. PASS
- Repeated staged id → accepted once, duplicate_count = 1. PASS

### Full regression

- Inline 1/2/5/20-file matrix: PASS (exact counts on every scenario).
- Inline 25-on-20-slot and 28→20 host-cap truncation: still rejected loud, 0 persists. PASS
- Sparse keys / delete-middle-row / same-name-different-content / exact-duplicate: PASS.
- Finance round-trip (RETURNED_TO_DEFICIENCY → resend): old attachments intact, 5 total. PASS
- Single-file `upload_document` action (scalar $_FILES): PASS.

**TOTAL: 180 passed, 0 failed.**

### Static suites

- `deficiency-finance-roundtrip-v08599.php` 20/0, `deficiency-finance-upload-handoff-v08598.php`
  23/0, `deficiency-portal-workflow-fixes-v08594.php` 28/0 — updated for the staged path.
- All remaining suites (v08592/v08593/v08595/v08597, b21/b22/b23, reporting-foundation)
  fail **identically on the pristine v0.8.59.9 package** — pre-existing stale version
  pins, verified by side-by-side baseline run, unrelated to this release.
- `php -l` clean on all production PHP; `node --check` clean on all 3 JS assets.

## Changed files

| File | Change |
|---|---|
| `includes/class-biawin-crm-receipt-staging.php` | **NEW** — staging endpoints + finalize/rollback |
| `includes/class-biawin-crm-deficiency-workflow.php` | staged-ids branch in `send_to_finance()`, public staging materialization wrapper, new hint text, `data-bcrm-stage-status` element, wider `accept` |
| `includes/class-biawin-crm-upload-normalizer.php` | `UPLOAD_FIELD` const, `RECEIPT_MIMES` widened to all image formats + pdf |
| `assets/portal-fixes.js`, `assets/frontend.js` | staged sequential upload on submit (progress, retry messaging, rollback), unlimited rows, cap logic removed |
| `assets/portal-fixes.css` | staging progress style (light/dark) |
| `includes/class-biawin-crm-frontend.php`, `admin/class-biawin-crm-admin.php` | `stageNonce` + `stagingToken` localization |
| `biawin-crm-integration.php`, `includes/class-biawin-crm-activator.php` | version 0.8.61.0 + require + upgrade chain |
| `readme.txt`, `CHANGELOG.md` | release notes |
| `tests/receipt-upload-runtime-v08600.php` | staged-path tests (28-file batch + guards) |

## Rollout notes

- Upgrade from 0.8.60.0/0.8.60.1 is code-only (no schema change).
- No hosting change required anymore for unlimited receipt counts; raising
  `max_file_uploads`/`post_max_size` remains recommended hygiene for other flows.
