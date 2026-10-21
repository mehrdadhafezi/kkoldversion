# QA report — v0.8.64.0 (After-Sales Phase 2: admin configuration foundation)

Date: 2026-10-01. Baseline: Phase 1 frozen at 0.8.63.2 (SHA256 `e2d150ca…5214`).
Out of scope, untouched: the full Complaints workflow UI, Phase 3, and the on-hold bug list.

## 1. Architecture audit (before any change)
| Area | Found | Decision |
|---|---|---|
| A. Tables | `complaints` (`subject` free text, `related_unit` free text, `resolution` text, `resolution_outcome` key, `status`, `owner_user_id`), `service_units` (+ Phase 1 `parent_unit_id`, `unit_type`, `can_receive_inquiry`, `requires_portal_response`, `display_order`), `service_unit_members` (supervisor/agent), `activities` (timeline), `customer_tags`, `case_actions` (deficiency only), `refunds.complaint_id` | Extend `complaints` (+`subject_id`) and `service_units` (+`inquiry_responders`). New: `catalog_items` (generic), `complaint_inquiries` (1→N). No inquiry table existed. |
| B. Classes | `Workflow_Service` (`create_complaint`, `resolve_complaint`, `can_manage_complaint`, option-based contact-outcome catalog), `Frontend` (profile complaint panel), `Admin` (units page and handler), `Activator` (migrations, ledger caps/seeds), `DB::log` | New `After_Sales_Config` (service) and `After_Sales_Config_Admin` (page), same pattern as `Historical_Audit`. |
| C. Admin pages | «نارضایتی‌ها» (list), «واحدها و اعضا», «تنظیمات» (contact outcomes in an option) | New «تنظیمات نارضایتی» under the CRM menu. |
| D. REST | `POST /complaints/{id}/resolve` → `resolve_complaint` (same server checks apply) | No new endpoints. |
| AJAX | `bcrm_create_complaint` (serializes the same profile form) | Covered by the form + service change. |
| E. Permissions | `bcrm_complaints`, `bcrm_manage_cases`, plus Phase 1 caps including `manage_dissatisfaction_subjects` / `_outcomes` | Reused. Only `manage_inquiry_units` is new. |
| F. Migrations | `MIGRATIONS` map, `GET_LOCK`, failure logging, version floor | `0.8.64.0 → migrate_0864_0`. |
| G. Tags/actions | `customer_tags` (e.g. `refund_reported`); `resolve_complaint` actions: `refund` → refund case, `service_request` → journey, `satisfied` → journey | Unchanged. Outcome `meta_json` reserves future actions. |

### Historical data (Step 10)
- **Subjects:** `subject` holds free text with no catalog, so mapping it to catalog items would be guesswork.
  Historical rows keep their text and get `subject_id` = NULL.
- **Outcomes:** `resolution_outcome` holds exactly the keys `satisfied` / `service_request` / `refund`, as
  written by the current code. Those keys are registered as system outcomes.
- **Assignments and tags:** not touched.

## 2. Tests and results
Environment:
- Real WordPress 7.0.3 + MySQL 8 (Docker `wordpress:cli-php8.1`, PHP 8.1.34).
- Throwaway databases; the release ZIP is installed by replacing files, never by deactivating.

**Update 0.8.63.2 → 0.8.64.0** (`tests/update-simulation-v08640/run-p2.sh`). Before the update:
- `simb.php customize`: users edited, disabled, deleted and re-roled, password changed, role/admin/user
  caps edited, services edited, deactivated, deleted and custom, units edited, business rows.
- `p2.php history`: historical complaints (free-text subjects; refund/satisfied/service_request outcomes),
  activities, tags, an assignment, and Phase 1 unit inquiry flags changed by an admin.

| # | Requirement | Result |
|---|---|---|
| 19 | Failed migration (`complaints` hidden) | PASS: `db_version` stays 0.8.63.2, failure logged with the error, no lock left (named lock free, no option lock) |
| – | Concurrency (lock held by another session) | PASS: no migration while locked |
| – | Retry on next request | PASS → 0.8.64.0 |
| 13 | Users | PASS: same set; ID, password hash, email, name, roles/caps, disabled flag identical |
| 14 | Roles | PASS: nothing removed, changed or created. The only addition is `manage_inquiry_units` on administrator and portal admin |
| 15 | Service catalog | PASS: byte-identical |
| 16 | Units / members | PASS: every pre-existing column identical (including admin-changed inquiry flags); new column = `members` |
| 17 | Complaints / customer data | PASS: original columns row-identical; `subject_id` NULL for all historical rows; 14 business tables count + CHECKSUM identical |
| 10 / 18 | Re-run migration twice (forced from 0.8.63.2 and from 0.8.62.28) | PASS 12/12 twice: users, roles, catalog, units, members, complaints, business, catalog items, inquiries, ledgers identical |
| 1–12 | Functional, through the real admin handlers (46 checks) | PASS 46/46 |

The functional checks cover:
- **Create:** create (with or without a key), duplicate key rejected.
- **Edit:** edit with the key kept, rename.
- **Complaint creation:** with subjects configured, free text is refused («موضوع نارضایتی را از فهرست
  انتخاب کنید.»); a new complaint stores `subject_id` + label snapshot.
- **Deactivate subject:** removed from the dropdown and refused for new complaints; the historical record
  keeps its reference and original label; the catalog row is never deleted.
- **Reorder:** up/down, re-sequenced 10/20/30; search works.
- **Outcomes:** create with `meta.actions` reserved; rename shown in the resolve form; deactivate removes it
  from the form and the server refuses it; historical `refund` still resolves to a label.
- **Units:** inquiry config saved without touching name, parent, supervisor or members; an identical
  re-save writes nothing; invalid scope rejected; responders follow the scope (supervisor vs members).
- **Inquiries:**
  - One complaint gets 3 inquiries to 3 units, 2 of them pending simultaneously.
  - The manual one records unit, result, notes, `contacted_at`, actor and `mode=manual`.
  - Portal inquiries have `mode=portal`, `status=pending` and no result yet.
  - Inquiries to a non-inquiry unit or a portal-disabled unit are refused.
- **Permissions:**
  - administrator / portal admin = 3/3 caps; after-sales manager = subjects + outcomes only.
  - agent, complaint agent, complaint supervisor and supervisor have none.
  - An agent is refused by every handler; an invalid nonce is refused.
  - The page renders tabs, search and per-unit status.

**Phase 1 regression** (`run-p1.sh`: 0.8.62.28 → 0.8.64.0 with `tests/update-simulation-v08630/verify.php`):
- **39/39 PASS** and units handler **10/10 PASS**.
- `verify.php` now compares `complaints` by its original columns row-by-row instead of `CHECKSUM TABLE`.
  The checksum changes only because a nullable column was added (confirmed: same 3 rows, 0 `subject_id`
  values). Every other table still uses `CHECKSUM TABLE`.

**Fresh install of 0.8.64.0:**
- Both new tables and both new columns created.
- 3 system outcomes and 0 subjects; inquiry units = services / finance_approval / loan / treasury.
- One WP user (none created); no plugin log lines.

**Suites:**

| Suite | Result |
|---|---|
| after-sales-config-v08640 (new, static) | 30/30 |
| after-sales-foundation-v08630 | 0 failures |
| final-hardening-v08632 | 16/16 |
| receipt-upload-runtime-v08600 (harness) | 609/0 |
| refund-regression | 141/0 |
| v08599, v08598, v08594, v08593, v08571, b1 | 0 failures |
| v08597 3, v08592 2, v08595 3, b21 2, b22 4, reporting-foundation 6, b23 3, v08591 2 | unchanged pre-existing baseline |
| PHP 8.1 lint | 60 files (including tests), 0 errors |
| `node --check` | 3 files, 0 errors |

`wp-content/plugins` contains only `biawin-crm-integration` after every update.

Note on the test environment:
- The `wordpress:cli` image's entrypoint boots WordPress once through WP-CLI before each command, so a
  failing step logs two `upgrade_migration_failed` rows.
- This was traced with a stack-trace probe: the plugin logs exactly once per request.

## 3. Test-file changes
- Version pins bumped to 0.8.64.0 in v08599, v08598, v08594 and v08593.
- `final-hardening-v08632` now checks version consistency (header = constant = readme) instead of the
  0.8.63.2 literal; its 0.8.63.2 changelog assertions are kept.
- `update-simulation-v08630/sim.php` + `verify.php`: added the row-level `complaints` comparison described
  above. This makes the check stricter on data, not weaker.
