# QA report — v0.8.64.1 (Phase 2.1: inquiry architecture hardening)

Date: 2026-10-01. Baselines: Phase 1 frozen at 0.8.63.2; Phase 2 = 0.8.64.0 (SHA256 `d7e9a053…67d8`, not overwritten).
Phase 3 has not been started.

## Root cause
In 0.8.64.0 the «واحدهای قابل استعلام» tab:
- rendered inputs for the unit's own `is_active` and `display_order`;
- had `save_unit_inquiry_config()` write both.

So the inquiry page could deactivate a unit. In addition, `unit_responders()` resolved `members` as
supervisor + agents, so "agents only" and "both" could not be expressed.

## Schema
No change. Existing columns are reused:
- `is_active` = unit active;
- `can_receive_inquiry` = inquiry enabled (Phase 1);
- `requires_portal_response`;
- `inquiry_responders` (`VARCHAR(20)`, now `members` | `supervisor` | `both`).

No migration was added because none is needed. The `MIGRATIONS` map is unchanged.

## Tests (real WordPress 7.0.3 + MySQL 8, Docker `wordpress:cli-php8.1`; run against the release ZIP)

### Phase 2.1: 0.8.64.0 → 0.8.64.1 (`run-p21.sh`)
The baseline was customized on 0.8.64.0:
- users, roles, services and units changed;
- a subject and a complaint created;
- a portal and a manual inquiry created;
- loan responders set to `supervisor`;
- treasury made inactive with inquiry enabled;
- an invalid stored responder value `xyz`.

| Test | Result |
|---|---|
| 10–15 strict identity before → after update (users + password hashes, roles, service catalog, units, members, complaints, inquiries, catalog items, business tables, ledgers) | 12/12 identical |
| 16 migrations re-run (forced from 0.8.64.0 and from 0.8.63.2) | 12/12 identical, twice |
| 1 create active unit | PASS |
| 2 disable inquiry → enabled 0, active 1, unit leaves provider list | PASS |
| 3 re-enable → 1/1 | PASS |
| 4 disable unit → active 0, inquiry enabled unchanged | PASS |
| 4 inactive unit not operational; inquiry refused | PASS |
| 4 treasury (inactive + enabled) refused, flags kept | PASS |
| 4 inquiry save cannot re-activate a unit | PASS |
| 5 `members` → member agents only (disabled member and supervisor excluded) | PASS |
| 6 `supervisor` → supervisor only | PASS |
| 7 `both` → union, no duplicates | PASS |
| 5–7 resolver read-only (users, memberships, roles unchanged); loan = `supervisor` preserved | PASS |
| 8 invalid modes (`admin`, `ALL`, `''`, `members;drop`) rejected; `"Members "` normalized; invalid boolean, array and unknown unit rejected | PASS |
| 8 stored `xyz` kept in DB, read as `members`; normalized only when that unit is saved | PASS |
| 9 only the 3 inquiry columns change; name, parent, supervisor, active, order, `updated_at`, members unchanged; other units untouched | PASS |
| 9 captured SQL: `UPDATE wp_bcrm_service_units SET can_receive_inquiry, requires_portal_response, inquiry_responders WHERE id` | PASS |
| 10 / 11 existing inquiry and complaint records unchanged after all functional actions | PASS |
| Agent refused; UI has radio responders, no active/order inputs, read-only unit status | PASS |
| **Total functional** | **31/31** |

### Phase 2 regression: 0.8.63.2 → 0.8.64.1 (`run-p2.sh`)
- **17 failed migration:** `db_version` stays 0.8.63.2, failure logged, named lock free, no option lock.
- **Concurrency:** no migration while another session holds the lock.
- **Retry:** the next request reaches 0.8.64.1.
- **Preservation:** 13/13. **Re-runs:** 12/12 twice. **Functional:** 47/47.

### Phase 1 regression: 0.8.62.28 → 0.8.64.1 (`run-p1.sh`, `verify.php`)
39/39, units 10/10.

### Fresh install of 0.8.64.1
- Clean, 3 system outcomes seeded.
- Inquiry units are active, enabled, portal, `members`.
- One user (none created); no plugin log lines.

### Suites
| Suite | Result |
|---|---|
| inquiry-hardening-v08641 (new) | 20/20 |
| after-sales-config-v08640 | 30/30 |
| after-sales-foundation-v08630 | 0 failures |
| final-hardening-v08632 | 16/16 |
| harness | 609/0 |
| refund-regression | 141/0 |
| v08599, v08598, v08594, v08593, v08571, b1 | 0 failures |
| v08597 3, v08592 2, v08595 3, b21 2, b22 4, reporting-foundation 6, b23 3, v08591 2 | unchanged pre-existing baseline |
| PHP 8.1 lint | 62 files, 0 errors |
| `node --check` | 3 files, 0 errors |

`wp-content/plugins` contains only `biawin-crm-integration` after every update.

## Test-file changes
- `p2.php` (Phase 2) follows the corrected contract:
  - posts `inquiry_enabled`;
  - asserts that posted `is_active`/`display_order` are ignored;
  - expects `members` = agents and `both` = supervisor + agents.
- Version pins bumped in v08599, v08598, v08594 and v08593.
