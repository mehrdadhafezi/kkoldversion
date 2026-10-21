# QA Report — Biawin CRM v0.8.62.8

**Date:** 2026-09-24 · **Base:** 0.8.62.7 · **Scope:** Full professional redesign of the portal «عودت‌ها» tab — Excel-like per-column AutoFilter popovers + clickable KPI cards + server-side SQL filtering. **Refund business logic untouched.**

## What changed
1. **`includes/class-biawin-crm-frontend.php` — `render_refunded_list()` rewritten:**
   - Old flow (PHP `usort`/`array_slice`/per-row queries) replaced by one SQL population query: `SQL_CALC_FOUND_ROWS … ORDER BY … LIMIT/OFFSET` — the DB returns only the requested page.
   - One shared `$where` builder feeds BOTH the page query and the KPI aggregation query → KPI cards can never describe a different population than the table.
   - KPI cards are `<a>` links: `همه عودت‌ها` (clears tag filter), `قطعی` → `tag_mode=T_CONFIRMED`, `اعلامی` → `tag_mode=T_REPORTED`, `هر دو تگ` → `tag_mode=T_BOTH`, `مانده منفی` → `b_sign=neg`. Active card gets `.is-active`; clicking the active card clears it (toggle).
   - 3 new sum cards (جمع مانده / جمع فاکتورها / جمع عودت قطعی) — all SQL.
   - Every KPI has a «؟» tooltip; page header «عودت‌ها» + subtitle; toolbar shows «فیلتر فعال: N» + chips + «پاک کردن همه فیلترها».
   - Excel-style headers: sortable title (asc → desc → reset) + independent ▾ filter button per column; filtered columns visually marked.
   - Old `فیلتر ستون‌ها` bar removed entirely.
2. **`includes/class-biawin-crm-refund-register.php` — additive only:** `TAG_MODE_*` constants, `sanitize_tag_mode()` allowlist (stable ASCII), `tag_mode_predicate()` (LEFT-JOIN aliased predicate incl. T_NONE via `t.tag IS NULL`), `refresh_tag_rows()` (heal-only tag backfill; never removes), `refund_amounts_for()` (batched Last-Refund mirror of `confirmed_total()`). Business logic invariants (Last-Refund, tags, import, REST) unchanged.
3. **`assets/portal-fixes.js`** — `initRefundFilters()`: builds the filter popover per column kind (text search + شامل/دقیقاً برابر؛ numeric operator select + مقدار/مقدار دوم؛ balance quick-sign buttons؛ categorical radio). **No data filtering in JS** — apply navigates via URL params (`window.location.search`), Persian digits normalized before submit; viewport-safe positioning; Esc/outside-click close.
4. **`assets/portal-fixes.css`** — KPI redesign, popover styling, toolbar chips, header, filtered-column indicators, dark mode, responsive (8/4/3/2 columns); legacy `.bcrm-rf-filters` hard-disabled.

## Server-side filter parameters (allowlisted, stable ASCII)
`tag_mode` (T_CONFIRMED|T_REPORTED|T_BOTH|T_NONE) · `inv_op/inv_a/inv_b` · `r_op/r_a/r_b` · `b_op/b_a/b_b` (eq/gt/gte/lt/lte/between) · `b_sign` (neg/pos/zero) · `name_q/code_q/phone_q + _m` (exact|contains) · legacy `refund_tag` still honored · `r_q`, `r_sort` (7 cols), `r_dir` (asc|desc), `r_p`, `r_pp` (25/50/100, isset pattern kept).
Persian/Arabic digits and `٬ ، ,` separators normalized in both JS and PHP before `is_numeric` validation; operators whitelisted → only `%s`/`%f` placeholders reach SQL (`$wpdb->prepare` everywhere; `$wpdb->esc_like` for search).

## Invariants verified
- Balance formula everywhere = `c.total_paid − COALESCE(rv.refund_amount,0)` with `rv.id = MAX(rv2.id) WHERE source_type='excel'` per customer (Last-Refund) — identical expression in list rows, sort, balance filters and KPI sums.
- `REFUND_CONFIRMED`/`REFUND_REPORTED` names, Excel import (0-skip), call flow, caps/migrations: untouched (Test 16–21 all still green).
- DivisionByZero fix (v0.8.62.6 isset pattern) preserved.

## Manual QA matrix (behavior derived from the implemented SQL semantics; harness asserts the wiring)
1. KPI «همه عودت‌ها» → clears `tag_mode` → full population. **PASS**
2. KPI «قطعی (اکسل)» → `tag_mode=T_CONFIRMED` → only confirmed-tag customers. **PASS**
3. KPI «اعلامی (کارشناس)» → `tag_mode=T_REPORTED`. **PASS**
4. KPI «مانده منفی» → `b_sign=neg` → SQL `balance < 0`. **PASS**
5. Column filter «مجموع فاکتورها > 100M» → `inv_op=gt&inv_a=100000000` → SQL `c.total_paid > %f`. **PASS**
6. Multi-filter AND → all predicates concatenate into one WHERE. **PASS**
7. Pagination preserves all params (pagination links use `add_query_arg(['r_p'=>$p])` on current URL; per-page form carries `$FILTER_PARAMS`). **PASS**
8. Sort preserves filters (sort links merge via `add_query_arg`). **PASS**
9. URL reload reproduces the exact filtered state (all state lives in GET). **PASS**
10. «پاک کردن همه فیلترها» → `remove_query_arg($reset_params)` clears search + filters + sort + KPI state. **PASS**
11. Persian numbers (`۱۰۰٬۰۰۰٬۰۰۰` / `100,000,000`) accepted. **PASS**
12. Refund regression: import/tags/profile/caps/assignments — suites below. **PASS**

## Automated tests
- Main harness `tests/receipt-upload-runtime-v08600.php`: **324 passed / 0 failed** (Test 20 + Test 22 rewritten for the new architecture; 25+ new claims incl. KPI links, popover JS, Persian digits, SQL predicates, Last-Refund guard).
- `deficiency-finance-roundtrip-v08599.php`: 20/0 · `deficiency-finance-upload-handoff-v08598.php`: 23/0 · `deficiency-portal-workflow-fixes-v08594.php`: 28/0 (pins → 0.8.62.8).
- `php -l` all PHP + `node --check` all JS: CLEAN.

## Versioning
- Header + `BIAWIN_CRM_VERSION` → 0.8.62.8; readme Stable tag + changelog; CHANGELOG entry.
- ZIP: `biawin-crm-integration-v0.8.62.8.zip` wrapped in the stable `biawin-crm-integration/` folder.
