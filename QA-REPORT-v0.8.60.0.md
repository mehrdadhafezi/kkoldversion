# QA Report v0.8.60.0

## Scope

Deficiency → Finance multi-file receipt upload **data integrity** fix. Finance round-trip
(`RETURNED_TO_DEFICIENCY`) behavior preserved.

## Root cause

In `Biawin_CRM_Deficiency_Workflow::send_to_finance()` (v0.8.59.9):

- `$receipt_amounts = array_values((array)$raw['receipt_amounts'])` **compacted** the amount
  rows to keys 0..N-1, while `case_documents[]` files kept their **original sparse request
  keys** (0,1,3,7,14,19… after middle-row deletions).
- The pairing loop iterated `array_keys($receipt_amounts)` (0..N-1) and tested
  `has_uploaded_file('case_documents', $idx)` against the **sparse** file array.
- Consequences with 20 selected files after row deletions:
  1. rows whose compacted index had no file at that key were **skipped** by the
     `if ($receipt_amount <= 0 && !$has_file) continue;` rule (silent per-row drop);
  2. files whose original key exceeded the compacted range were **never enumerated**;
  3. some rows matched *another row's* file or amount (index confusion), producing apparent
     duplicates while unique files disappeared;
  4. the old signature dedup used `continue` on conflict and could still mark the case
     FINANCE_REVIEW_PENDING — silent partial loss.
- Net effect: ~15 of 20 files persisted, duplicates present, unique files missing.

## Fix

- **Canonical `$_FILES` normalization** (`includes/class-biawin-crm-upload-normalizer.php`,
  new class): one entry per real uploaded payload preserving the ORIGINAL request key, name,
  type, tmp_name, error, size; per-key structural validation; malformed entries rejected
  (never remapped, never borrowed); SHA-256 content identity helper; server-limit diagnostics
  (`max_file_uploads` truncation, declared-vs-received count, `CONTENT_LENGTH` vs
  `post_max_size`); safe count/index logging.
- **Order-pairing contract** in `send_to_finance()`: a browser posts `receipt_amounts[]`
  and `case_documents[]` in the same DOM row order and PHP densifies the values of both
  arrays, so rows are paired by validated order after a strict count check — never by
  re-indexing one side with `array_values()` while the other keeps sparse keys, and never by
  guessing. Count mismatch ⇒ explicit failure, zero persists.
- **No unsafe fallbacks**: no first-file/last-file/positional fallback anywhere;
  `save_uploaded_document()` resolves the exact per-row entry and hands `media_handle_upload()`
  a scalar per-row `$_FILES` field (`bcrm_case_document_upload`).
- **Request-scoped dedup**: SHA-256 of the real tmp content. Same filename + different
  content ⇒ both accepted. Identical content twice ⇒ only the first occurrence persists,
  the duplicate is counted and reported (`duplicate_count`), nothing else is lost.
- **All-or-nothing rollback**: any row failure (missing amount, missing file, upload error,
  duplicate-only request) deletes all same-request attachment rows and media files via
  `wp_delete_attachment()`; previous-round attachments are never touched. The case is NOT
  moved to `FINANCE_REVIEW_PENDING` on failure.
- **Full accounting** returned with every result and logged
  (`receipt_upload_summary`, `receipt_row_failed_rollback`, `server_limit_truncation`, …):
  `received_count`, `valid_count`, `uploaded_count`, `persisted_count`, `duplicate_count`,
  `failed_count`, uploaded/failed/duplicate row keys, attachment IDs.
- DB row is inserted only after a successful media upload; media file is deleted if the DB
  insert fails.

## Runtime test results (tests/receipt-upload-runtime-v08600.php — real files, SQLite-backed)

| # | Scenario | Result |
|---|----------|--------|
| 1 | **20 UNIQUE FILES (release blocker)** | **selected 20 / received 20 / uploaded 20 / persisted 20 / unique 20 / duplicates 0 / missing 0 — PASS** |
| 2 | Sparse indexes 0,2,5,8,11,17,21,30 | 8/8 persisted, per-row amounts preserved — PASS |
| 3 | Delete middle rows (20 rows − 4/9/13 + 3 new) | 20/20 persisted, exact identity set — PASS |
| 4 | Same filename, different content | 2/2 persisted — PASS |
| 5 | Intentional exact duplicate | 1 persisted, duplicate_count 1, no unique file lost — PASS |
| 5b | File without matching amount row | fails loud, zero persists — PASS |
| 6 | Partial failure at row 3 of 5 | full rollback (0 DB rows, 0 live media), status unchanged — PASS |
| 7 | Truncated request (25 rows, 20 received) | explicit server-limit error, zero persists — PASS |
| 8 | Malformed `$_FILES` entries | rejected explicitly, no borrowing — PASS |
| 9 | Regression 1/2/5/20/25 | exact counts every time — PASS |
| 10 | RETURNED_TO_DEFICIENCY round-trip | 3 + 2 = 5 historical attachments, none deleted, FINANCE_REVIEW_PENDING — PASS |
| 11 | Single scalar `case_document` action | 1/1 — PASS |
| 12 | Production bug shape (sparse keys + dense amounts) | 4/4, row-by-row pairing verified via notes — PASS |

**TOTAL: 103 passed, 0 failed.**

Legacy source-string tests: v0.8.59.9 / v0.8.59.8 / v0.8.59.7 / v0.8.59.4 suites re-pointed
to 0.8.60.0 where the fix intentionally changed semantics (all green). The v0.8.59.7-based
suites (v08592/v08593/v08595/v08597/reporting-foundation) still assert the historical
0.8.59.7 version pin — those failures are **pre-existing on the pristine v0.8.59.9 package**
(verified by running them against the untouched extraction) and are unrelated to this fix.

## Quality gates

- `php -l` clean on all production PHP files and all tests.
- `node --check` clean on all three JS assets (admin.js, frontend.js, portal-fixes.js).
- Package diff vs pristine v0.8.59.9: only the intended files changed.

## Safety

- Code-only patch; no schema migration, no customer merge, no bulk invoice migration.
- No Guard change; no Run #7 continuation; no Run #8 implementation.
- Previous-round attachments are never deleted or overwritten; success still transitions
  the case to `FINANCE_REVIEW_PENDING`.

## Deliverable

- ZIP: `biawin-crm-integration-v0.8.60.0-receipt-upload-data-integrity.zip`
- Version: 0.8.60.0 (header, `BIAWIN_CRM_VERSION`, activator upgrade chain 0.8.59.9→0.8.60.0, readme stable tag, CHANGELOG)
