# QA Report — Biawin CRM v0.8.62.9

**Date:** 2026-09-24 · **Base:** 0.8.62.8 · **Scope:** Critical KPI population/amount fix + per-card amount + Excel export. **Business logic untouched.**

## 1. The bug (reproduced from production screenshot)
- «همه عودتی‌ها» = 28,876 while قطعی = 1,676 → the 0.8.62.8 KPI query had **no base population predicate**: `COUNT(*)` ran over ALL `customers` rows.
- «هر دو تگ» = 1,676 while اعلامی = 0 → the same query counted `t.tag IS NOT NULL` (OR logic), not "has both tags".
- Sum cards could double-count through the same row-level JOINs.

## 2. The fix
1. **Base population predicate** (inside the shared WHERE builder `refunded_query_parts()`):
   `(EXISTS(tag REFUND_CONFIRMED OR REFUND_REPORTED) OR EXISTS(refund_registers source_type='excel'))` — «همه عودت‌ها» can never count all CRM customers again.
2. **One row per customer, everywhere:** the KPI query and the page query now have **no row-level tag/refund JOINs**. The displayed tag (confirmed-priority via `ORDER BY tx.tag LIMIT 1`) and the Last-Refund amount (`ORDER BY rr.id DESC LIMIT 1`, `source_type='excel'`) come from LAST-ID subqueries in SELECT. `COUNT(*) == COUNT(DISTINCT customer)`; SUMs cannot fan out.
3. **«هر دو تگ» = AND:** tag-count subquery `= 2`.
4. **KPI ↔ table consistency:** one WHERE builder shared by list / KPI / export; the same predicate also feeds the table rows, so a KPI click shows exactly the KPI population.

## 3. Per-card count + amount + tooltip (all from one SQL aggregation)
| Card | Count | Amount (SUM … CASE inside the same query) |
|---|---|---|
| همه عودت‌ها | customers with confirmed OR reported tag | Last-Refund amount each (total_amount) |
| قطعی (اکسل) | customers with REFUND_CONFIRMED | sum of last excel refund per customer (confirmed_amount) |
| اعلامی (کارشناس) | customers with REFUND_REPORTED | **0** — agent-reported refunds carry no amount; none invented |
| هر دو تگ | both tags simultaneously (AND) | sum of last excel refund of that AND population (both_amount) |
| مانده منفی | customers with balance < 0 | **sum of their actual negative balances** (negative_amount, red) |
| جمع مانده / فاکتورها / عودت قطعی | — | SUM over the filtered population |

Balance formula everywhere: `total_paid − last excel refund (MAX(id), source_type='excel')` — unchanged (Last-Refund). Tooltips updated to describe both count and amount.

## 4. Excel export
- Toolbar button «⤓ خروجی اکسل» → `?bcrm_rf_export=1&_wpnonce=…` on the CURRENT URL.
- Guards: portal view cap already enforced; export re-checks `bcrm_refund_register || bcrm_view_refunded` + `check_ajax_referer('bcrm_rf_export')`.
- Same `refunded_query_parts()` WHERE + `refunded_fetch_rows()` rows with the request's sort; **no pagination**; hard cap 50,000 rows.
- Writer: minimal XLSX via ZipArchive (inline strings, 5-part package — no PhpSpreadsheet dependency); CSV fallback (UTF-8 BOM) when ZipArchive is missing.
- Columns: مشتری، CustomerID، موبایل، مجموع فاکتورها، مبلغ عودت (آخرین قطعی)، مانده، تگ‌ها.

## 5. Automated tests
- Main harness: **348 passed / 0 failed** — Test 23 added (20 claims: base population, no-JOIN KPI, AND both-tags, per-population amounts incl. negative-amount semantics, reported=0, amount subvalue markup+CSS, shared-layer consistency, export nonce/caps/cap/writer/fallback).
- Legacy suites (pins → 0.8.62.9): roundtrip 20/0 · handoff 23/0 · workflow-fixes 28/0.
- `php -l` all PHP + `node --check` all JS: CLEAN.

## 6. Expected numbers after deploy (per screenshot data)
- همه عودت‌ها: **1,676** (+ مبلغ = جمع عودت قطعی) · قطعی: 1,676 · اعلامی: 0 · هر دو تگ: **0** (AND) · مانده منفی: 116 (−39,036,465,003) · جمع فاکتورها = 427,820,107,858.

## Versioning
- Header + `BIAWIN_CRM_VERSION` → 0.8.62.9; readme Stable tag + changelog; CHANGELOG entry.
- ZIP: `biawin-crm-integration-v0.8.62.9.zip` wrapped in stable `biawin-crm-integration/`.
