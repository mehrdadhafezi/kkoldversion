# QA report — v0.8.63.2 (Phase 1 final hardening)

Date: 2026-10-01. Scope: production disk/export failures, temp/backup hygiene, upgrade safety.
Phase 2 / Complaints workflow: **not started** (per instruction). On-hold bug list (S1/S2/S5…): untouched.

## Environments
- **Linux:** Docker `wordpress:cli-php8.1` (PHP 8.1.34, zip, mysqli) + `mysql:8`. Real WordPress 7.0.3 core,
  throwaway databases, plugin installed from the release ZIP.
- **Windows:** PHP 8.4.4 (static suites, SQLite harness).
- Production failure conditions are reproduced for real, not mocked:
  - `php -d disable_functions=disk_free_space,disk_total_space`.
  - Size-limited tmpfs mounts as `WP_TEMP_DIR` / `BCRM_BACKUP_DIR` for genuine ENOSPC.
  - `/dev/full`.

## 1. Production fatal reproduced, then fixed
| Build | `disk_free_space` | Result |
|---|---|---|
| 0.8.63.1 | available | XLSX (611 KB) |
| 0.8.63.1 | **disabled** | **`PHP Fatal error: Uncaught Error: Call to undefined function disk_free_space()` admin.php:2517** (the production error) |
| 0.8.63.2 | available | XLSX, 3,001 rows, valid XML |
| 0.8.63.2 | **disabled** | XLSX, 3,001 rows, valid XML, no fatal, empty stderr |
| 0.8.63.2 | disabled + `display_errors=1`, `error_reporting=-1` | XLSX, 0 PHP messages inside the file |

## 2. Export matrix (0.8.63.2, 3,000 seeded customers)
| Case | Condition | Result | Temp left |
|---|---|---|---|
| E4 | free space reported 1 MB (filter) | CSV fallback, 3,001 lines, logged `low_disk` | 0 |
| E5 | **real ENOSPC** (200 KB tmpfs) + dfs disabled | write fails at row 45, cleanup, CSV 3,001 lines, logged `xlsx_write_failed` | 0 |
| E6 | real ENOSPC, dfs available | pre-check → CSV (`low_disk`) | 0 |
| E7 | real ENOSPC, pre-check fooled (filter claims plenty) | `xlsx_write_failed` → CSV | 0 |
| E8 | temp dir missing/unwritable | CSV (`temp_unavailable`) | – |
| R1/R2 | refund XLSX normal / real ENOSPC | XLSX 4,001 rows / CSV 4,001 lines | 0 / 0 |
| W1/W2 | workflow XLSX normal / real ENOSPC | XLSX 4,001 rows / Persian `wp_die` (503), 0 bytes body, logged | 0 / 0 |
| S1 | stale sweep | old `bcrm-tmp-*` removed; fresh `bcrm-tmp-*` and a foreign file kept | – |

Export data semantics are unchanged: same columns and rows; CSV is used only when XLSX is impossible.

## 3. Backups
- **Retention:** 5 consecutive backups with `BCRM_BACKUP_DIR` → exactly the newest 3 remain (prune logged).
- **Integrity:** NDJSON, 9,035 lines, 0 invalid JSON.
- **Real ENOSPC** (300 KB tmpfs): `ok:false` with a Persian message. The partial file is deleted; only the guard
  files remain. Logged `crm_backup_failed`.
- **Default location** (`uploads/biawin-crm-backups`):
  - Guard files: `.htaccess` ("Require all denied" / "Deny from all"), `web.config`, `index.php`, `index.html`.
- All of this ran with `disk_free_space` disabled.

## 4. Upgrade / preservation (real WordPress, files replaced in place, never deactivated)
**Baseline 0.8.62.28 → 0.8.63.2** (`tests/update-simulation-v08630`):
- `verify.php`: **39/39 PASS**; units handler **10/10 PASS**.
- **Forced failure:** `service_units` hidden.
  - The migration failed, and `db_version` stayed at 0.8.62.28.
  - The named lock was free and no option lock was left.
  - `upgrade_migration_failed` was logged with the error.
- **Concurrency:** while another MySQL session held the migration lock, the request did not migrate.
- **Retry:** the next request migrated to 0.8.63.2 with state identical to the success path.

**Baseline 0.8.63.1 → 0.8.63.2**, baseline-agnostic customizer `simb.php`:
- Customizations applied first:
  - users: changed password, disabled, deleted, re-roled, custom cap, edited profile;
  - roles: custom role cap, admin cap, removed cap;
  - services: edited, deactivated, deleted, custom;
  - units: renamed, re-parented, deleted, plus a member;
  - business rows.
- before → after: **8/8 identical**. This covers users (hash, roles, caps, disabled flag), roles, the service
  catalog, units, members, business-table CHECKSUMs and ledgers.
- Two more forced full migration replays: **8/8 identical** each time.
- `wp-content/plugins` contains only `biawin-crm-integration` (no versioned copies).

## 5. Suites
| Suite | Result |
|---|---|
| final-hardening-v08632 (new) | 16/16 on Windows PHP 8.4 and Linux PHP 8.1 (incl. `/dev/full`) |
| receipt-upload-runtime-v08600 (harness) | 609/0 |
| refund-regression | 141/0. Also 141/0 on PHP 8.1 with dfs disabled |
| after-sales-foundation-v08630 | 0 failures |
| v08599, v08598, v08594, v08593, v08571, b1 | 0 failures |
| v08597 3, v08592 2, v08595 3, b21 2, b22 4, reporting-foundation 6, b23 3, v08591 2 | identical to the pre-existing baseline (unchanged since before 0.8.63.0) |
| PHP lint (8.1) | 34 files, 0 errors |
| `node --check` | 3 files, 0 errors |

Note: on PHP 8.1 in Docker the harness shows 608/1. The one failure is an assertion that reads
`../build-zip-v08623.php` outside the mounted plugin folder; it is environmental and passes on the host.

## Test-only adjustments (no assertion weakened)
- Harness: also loads `class-biawin-crm-file-safety.php`, as the plugin does.
- The "ob flush before headers" assertion now checks `File_Safety::send_file` (the same loop, moved).
- The diagnostics assertion now requires the File_Safety route and forbids a raw `disk_free_space(` call.
