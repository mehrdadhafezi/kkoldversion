# Ownership Reconciliation C1 implementation report

C1 only. No commit, deployment, C2, ownership mutation, UI, or runtime guard.

## Baseline

- Branch: `feature/ownership-reconciliation`.
- Starting HEAD: `14cccf90850f5153db0ed08b0103de92fc09bac0`.
- Tag: `baseline-v0.8.65.17-ownership-approved`, annotated tag dereferences to starting HEAD.
- Working tree was clean; public plugin version was `0.8.65.17`.
- No `.gitattributes` existed. Added `* text=auto`; no mass normalization.
- Read STRUCTURE.md, the Deficiency/Finance state machine, approved architecture contract and frozen implementation design. Checked the existing activator, DB, Auth, REST, customer mutation, import and workflow integration points.
- Upstream implementation reference: https://developer.wordpress.org/reference/functions/dbdelta/ and https://developer.wordpress.org/plugins/creating-tables-with-plugins/ . Role provisioning reference: https://developer.wordpress.org/reference/classes/wp_roles/add_role/ .

## Files changed

- `.gitattributes`: minimal text handling.
- `biawin-crm-integration.php`: version and three class includes.
- `includes/class-biawin-crm-activator.php`: additive migration, fresh-install schema ensure and existing ledger integration.
- `includes/class-biawin-crm-ownership-schema.php`: schema only.
- `includes/class-biawin-crm-ownership-authorization.php`: role/capability definitions only.
- `includes/class-biawin-crm-ownership-migration-audit.php`: opt-in read-only batch audit.
- `tests/ownership-c1.php`: standalone tests using explicit WordPress/wpdb doubles.
- `CHANGELOG.md`, `readme.txt`: release metadata (also correct the previously stale readme Stable tag).
- `docs/OWNERSHIP-RECONCILIATION-C1-REPORT.md`: this report.

## Database

Prefix follows `$wpdb->prefix . 'bcrm_'`. Seven new tables contain the fields/nullability/defaults specified in implementation design section 3:

| Table | Secondary indexes (PK id on every table) |
|---|---|
| ownership_cases | unique case_code; status; risk_level/status; current_owner_customer_id/status; claimant_customer_id/status; assigned_to_user_id/status; supervisor_approval_status/status |
| ownership_case_assets | unique case_id/asset_type/asset_id; asset_type/asset_id; source_customer_id; target_customer_id |
| ownership_case_amendments | case_id |
| ownership_locks | entity_type/entity_id/released_at; customer_id/released_at |
| customer_holds | customer_id/status; case_id/status |
| ownership_events | case_id/id; customer_id/created_at; asset_type/asset_id/created_at; event_type/created_at |
| ownership_otp_challenges | case_id |

Four nullable columns added to invoices: `canonical_phone_normalized VARCHAR(30)`, `canonical_phone_source VARCHAR(30)`, `canonical_phone_updated_at DATETIME`, `canonical_phone_case_id BIGINT UNSIGNED`. No additional invoice indexes or exactly-one/NOT NULL constraints. `invoice_phones` is unchanged. No backfill, history deletion, or owner update.

Version `0.8.65.18` is the smallest next release, needed by the existing public-version-driven migration runner. Added migration `0.8.65.18 => migrate_0865_18`; capability marker is 9 (marker 8 introduced C1; marker 9 applies the requester-capability correction without a public version or schema change). DDL uses dbDelta for new tables, checks required fields/index names, and conditionally adds missing invoice columns. Failures throw, restore error suppression and keep the release retryable; the current version is stamped only after schema success.

For the approved 0.8.65.17 baseline upgrade, skip full legacy activation after C1 DDL. Otherwise activation would repair active assignments and backfill Deficiency cases, violating C1 isolation. Fresh activation still uses the existing activation path plus C1 schema. Upgrades from older baselines retain the existing legacy activation behavior; those are outside the supplied baseline and need staging verification.

## Roles and capabilities

New roles: `bcrm_ownership_specialist`, `bcrm_ownership_supervisor`; both have read and bcrm_work_cases for existing CRM authentication compatibility.

Specialist operational capabilities:

```text
submit_ownership_claim
view_own_ownership_claims
view_ownership_cases
review_ownership_cases
amend_ownership_case
request_ownership_evidence
record_ownership_contact
issue_ownership_otp
verify_ownership_otp
approve_normal_ownership_transfer
reject_ownership_claim
record_ownership_withdrawal
manage_ownership_evidence
view_ownership_audit
```

Supervisor adds `assign_ownership_cases`, `approve_high_risk_ownership_transfer`, `manage_ownership_policy`. Administrator and CRM portal administrator receive the supervisor set under the approved permission matrix. The ordinary core profile/Deficiency roles `bcrm_agent`, `bcrm_supervisor`, `bcrm_deficiency_agent`, and `bcrm_deficiency_supervisor` receive ONLY `submit_ownership_claim` and `view_own_ownership_claims`. These roles already perform customer-profile work through `Biawin_CRM_Frontend::can_access_customer()` (assigned profile or `bcrm_manage_cases`) and Deficiency work through `Biawin_CRM_Deficiency_Workflow::can_access_case()` (assigned processor or Deficiency supervisor). They receive none of the review, approval, assignment, amendment, OTP, evidence management, audit or policy capabilities. Other department role mappings are unchanged. These request capabilities do not themselves authorize access to additional customer profiles. Existing ledger preserves removed grants, explicit denials and deleted roles. No endpoints consume these capabilities yet; assignment/risk/second-approval enforcement is deferred.

## Legacy audit

Call after successful schema migration in a trusted WordPress context:

```php
$report = Biawin_CRM_Ownership_Migration_Audit::audit_batch(0, 200);
// Repeat using $report['next_after_id'] while $report['has_more'] is true.
```

No hook runs this automatically. Uses keyset pagination, limit 1..1000 invoices, indexed related-data queries and SELECT only. Counts apply to the returned batch; aggregate in the caller for a whole inventory. Returns invoice IDs, relation counts, normalized candidates, issue reasons, mismatching service IDs, and completed-profile invoices lacking an initial lock.

- HEALTHY / `CLEAN`: one valid normalized relation with no detected conflict.
- AUTO_REPAIRABLE / `DETERMINISTIC_REPAIR`: formatting or duplicate representations normalize to one candidate, with no detected conflict. This is a recommendation only; C1 performs no repair.
- AMBIGUOUS / `AMBIGUOUS_REVIEW`: zero phones, multiple distinct candidates, invalid normalization, canonical conflict, missing owner/phone identity, duplicate matching phone identities, phone-owner mismatch, or service-owner mismatch.

No guessing among candidates; no queue inserts, lock creation or data mutation. Read errors throw rather than masquerade as empty/healthy data. This invoice-oriented audit inspects linked phone representations and normalized matches; it is not a global unlinked-phone deduplication audit. Pages are a live inventory, not a frozen transactional snapshot.

Mutation route inventory for later C3/C4: Customer Service attach_invoice, attach_invoice_and_phones, collapse_empty_profile, create_manual_invoice[_with_phone], add_phone, add_phone_to_invoice, phone verification; Import Service phone upserts/invoice writes/merge paths; REST create_interaction and Deficiency action dispatch; shared completion/recalculation paths. No guards added to these routes in C1.

## Verification actually executed

- `php -n tests/ownership-c1.php`: PASS, 179 checks using explicit WordPress/wpdb doubles. Includes repeat schema/role registration, custom prefix, nullable columns, incomplete/failed DDL, retry/version handling, older activation failure, role deletion/deny/removal preservation, requester-only privilege matrix across all four roles, existing-C1 marker-8-to-9 repair with no schema/audit queries, preservation of requester denials/removals/deleted roles, all specialist and supervisor privileges, audit classes, pagination, owner/service/normalization collisions, SELECT-only audit and read failure.
- `php -n -l` on every PHP file: PASS, 41 files.
- Static design comparison: every approved field exists in all seven new table definitions.
- Static baseline comparison: 21 protected workflow/reporting/DB/admin/assets files match the approved baseline, ignoring CRLF/LF only.
- `git diff --check`: PASS.
- No pre-existing executable automated test suite, Composer config, or PHPUnit config was found. Existing acceptance documents require runtime/manual verification.
- `wp core is-installed`: BLOCKED; checkout is not a WordPress installation. Local PHP ini also emits missing XML-extension warnings, so standalone tests/lint used `php -n`.

Not executed: real dbDelta/MySQL repeat migration, fresh WordPress activation, actual stored-role upgrade, concurrent upgrade locking and browser/customer/finance regression acceptance. Test-double success is not a claim of live DB idempotence or runtime regression success.

## Regression and risks

No modifications to Deficiency or Finance states/transitions, receipt reviews, Customer/Invoice/Phone mutations, attach/merge, imports, profile completion, allocation/assignment workflows, reporting, Auth/REST, frontend/admin layouts, JS or CSS. The approved-baseline upgrade avoids activation data repairs. Public version/readme/changelog and additive provisioning are the only existing integration surfaces changed.

The requester-side permission omission was corrected under the explicit business rule: ordinary profile/Deficiency workers can submit and view their own claims. No unresolved business-rule conflict remains. Runtime/database validation remains necessary before rollout; populated-table ALTER may take a metadata lock. Ownership tables/capabilities are infrastructure only: no UI, guards, OTP delivery, transfer, amendments workflow, risk engine or supervisor decisions are operational yet. Stop here at C1.

## C1 requester correction

Correction-only files: `includes/class-biawin-crm-ownership-authorization.php`, `includes/class-biawin-crm-activator.php`, `tests/ownership-c1.php`, and this report. The existing ledger provisions only the two request-side capabilities for the four core profile/Deficiency roles listed above. Capability marker 9 causes installations already at plugin version 0.8.65.18/marker 8 to synchronize once; repeated registration is stable. Public version and DB migration are unchanged. No workflow, endpoint, state machine, UI, mutation path or C2 work was added. No commit was made.

Reran the same C1 verification: 179 standalone checks pass, all 41 PHP files lint, all approved schema fields are present, 21 protected files match baseline and `git diff --check` passes. Live WordPress/MySQL/browser acceptance remains unavailable as described above.

## Git snapshot (uncommitted)

`git diff --stat` (tracked files only; Git omits untracked additions):

```text
 CHANGELOG.md                            |  8 +++++++
 biawin-crm-integration.php              |  7 +++++--
 includes/class-biawin-crm-activator.php | 37 ++++++++++++++++++++++++++++-----
 readme.txt                              |  2 +-
 4 files changed, 46 insertions(+), 8 deletions(-)
```

`git status --short`:

```text
 M CHANGELOG.md
 M biawin-crm-integration.php
 M includes/class-biawin-crm-activator.php
 M readme.txt
?? .gitattributes
?? docs/OWNERSHIP-RECONCILIATION-C1-REPORT.md
?? includes/class-biawin-crm-ownership-authorization.php
?? includes/class-biawin-crm-ownership-migration-audit.php
?? includes/class-biawin-crm-ownership-schema.php
?? tests/
```
