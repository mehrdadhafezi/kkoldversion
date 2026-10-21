# QA Report — Biawin CRM 0.8.62.10

Date: 2026-09-24. Input: original uploaded 0.8.62.9 ZIP.

## Change
Undefined refund alias was retained in WHERE after row JOINs were removed.
A single refunded_amount_expressions() now supplies the scalar latest Excel
refund and balance expressions to filters, KPI and table/export SELECTs.
Latest means source_type='excel' ORDER BY id DESC LIMIT 1, never SUM.
Absent refunds remain NULL in refund fields and zero only in balance/aggregate
calculations. Positive/nonnegative remains >= 0; negative < 0; zero = 0.
No database schema, import, tag storage or other service changes.

Related query defects corrected: refund/tag sorting referenced absent SELECT
aliases; tag filtering referenced the removed t JOIN. Local EXISTS predicates
now implement confirmed/reported/both (AND)/none without row multiplication.

## Executed validation
- Regression: **141 passed / 0 failed** using production private methods via
  reflection and an isolated SQLite fixture with PDO exceptions enabled.
- Executes actual shared WHERE, paginated table / export fetch SQL, and the
  actual KPI query reached through render_refunded_list(). Stops rendering at
  the KPI result to avoid requiring a live WordPress UI.
- Only SQL_CALC_FOUND_ROWS is stripped for SQLite. Financial SQL is unchanged.
- Covers negative/positive/zero, comparisons/ranges, numeric search, combined
  filters, tag modes, legacy tag input, sorting, pagination/export consistency,
  KPI counts/amounts, multiple Excel histories, later non-Excel records, missing
  refunds, excluded non-refund customers, and multiple phone records.
- Canonical refund SQL occurs exactly once; no obsolete refund alias remains
  in the production frontend source; export calls the tested shared path.
- Running regression against the original 0.8.62.9 source fails at the negative
  filter with the undefined-alias guard, confirming the test detects the bug.
- PHP syntax: **33 files passed** (including the new regression).
- JavaScript syntax: **3 files passed**.

## Limits
The original ZIP contains historical QA reports but **no executable historical
harness/legacy suites**. Their reported 348/20/23/28 passes were not rerun and
are not claimed as current verification. Docker access is unavailable in this
environment, so no MySQL/MariaDB or live WordPress integration was performed.
XLSX serialization, UI rendering and production counts were not exercised.

## Reproduction
With PHP CLI, mbstring and pdo_sqlite enabled:
`php tests/refund-regression.php`
Set BCRM_TEST_ROOT to an extracted alternate plugin root to run against it.
Windows environment used PHP 8.4.4 with a clean configuration (-n) and explicit
mbstring/pdo_sqlite extensions to avoid unrelated local php.ini startup warnings.

## Packaging
Header, version constant and readme stable tag: 0.8.62.10.
ZIP uses only biawin-crm-integration/ at its root. CRC and exact file contents
are verified against the working release, with no baseline files included.
