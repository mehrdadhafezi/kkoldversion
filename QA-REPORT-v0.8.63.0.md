# QA Report — v0.8.63.0 (After-Sales Phase 1: Foundation)

**Date:** 2026-09-30 · **Scope:** S4 + L2 fixes, unit hierarchy, roles, capabilities, migration. No dissatisfaction/inquiry/treasury/refund-scheduling UI and no profile redesign (later phases).

## Root causes
- **S4:** `Biawin_CRM_Activator::activate()` runs on activation, on «ترمیم دیتابیس», and on EVERY version bump (`maybe_upgrade()`). It called `seed_default_operations_staff()` / `seed_default_specialist_staff()` which created 27 + 8 staff accounts and one agent per service unit with a hard-coded password, re-created deleted accounts, re-applied the default role (`set_role`), rewrote display names and forced `bcrm_disabled=0`. `seed_default_services()` created a supervisor login per default service. `ensure_roles_and_caps()` ran on every request and re-granted every defined capability, undoing any capability an admin removed.
- **L2:** `seed_default_services()` UPDATEd the 8 default services (name, credit_cost, unit_key, is_active, sort_order) back to hard-coded values on every activate, force-deactivated legacy service codes, and reset each default unit's name/supervisor/active state.

## Changes
| File | Change |
|---|---|
| `includes/class-biawin-crm-activator.php` | user seeders removed; ledger-based additive role/cap sync (marker v4, not per request); insert-only service/unit seeding with seed ledgers; `service_units` hierarchy columns + indexes; initial after-sales units; migration `0.8.63.0`; valid `refund_registers` CREATE |
| `admin/class-biawin-crm-admin.php` | «واحدها و اعضا» page (hierarchy/type/flags/order/active/edit, no N+1); unit handler validates cycles + CRM-only members, audit log; after-sales caps grantable per user |
| `includes/class-biawin-crm-auth.php` | role labels (new roles; «نارضایتی‌ها») |
| `includes/class-biawin-crm-frontend.php` | portal menu label «واحدها و اعضا» |
| docs (`*.md`) | default password redacted; `DEFAULT-*-USERS*.md` excluded from the ZIP (staff usernames) |

## Database
`wp_bcrm_service_units`: `parent_unit_id BIGINT NULL`, `unit_type VARCHAR(40) DEFAULT 'service'`, `can_receive_inquiry TINYINT`, `requires_portal_response TINYINT`, `display_order INT`; indexes `parent_unit (parent_unit_id)`, `type_active_order (unit_type,is_active,display_order)`; `wp_bcrm_service_unit_members` index `user_role (user_id,member_role)`. Options: `biawin_crm_caps_ledger`, `biawin_crm_service_seed_ledger`, `biawin_crm_unit_seed_ledger`, `biawin_crm_service_units_parented_0863`. No table/row is dropped or deleted.

## Units
`after_sales` (خدمات پس از فروش) → `dissatisfaction`, `services` (+ existing service cartables), `finance_approval`, `loan`, `treasury`. Membership = existing `service_unit_members` (supervisor/agent per unit; a user may be in several units).

## Roles / capabilities
New roles: `bcrm_after_sales_manager`, `bcrm_loan_supervisor`, `bcrm_loan_agent`, `bcrm_treasury_supervisor`, `bcrm_treasury_agent`. Reused: `bcrm_complaint_supervisor/agent` (= نارضایتی), `bcrm_service_*`, `bcrm_financial_*`.
Capabilities: view_after_sales, manage_after_sales, view_dissatisfaction, manage_dissatisfaction, assign_dissatisfaction, process_dissatisfaction, view_after_sales_reports, manage_after_sales_settings, manage_dissatisfaction_subjects, manage_dissatisfaction_outcomes, view/respond_finance_inquiries, view/respond_loan_inquiries, view/respond_service_inquiries, view_treasury, manage_treasury, manage_treasury_capacity, approve_refund_schedule, process_refund_payment.

## QA
- **Real update simulation** (WordPress 7.0.3 + MySQL 8.4.3, `tests/update-simulation-v08630/`): old 0.8.62.28 activated → admin customizations (password change, deleted/re-roled/disabled/renamed users, custom role/admin/user caps, edited/deactivated/reactivated/custom services, renamed/deactivated units) + business data → ZIP replaced **without deactivation** → upgrade → **39/39 PASS**: users (IDs, password hashes, roles, caps, disabled flag, profile) unchanged; deleted users stay deleted; roles/caps unchanged + additions; service catalog byte-identical; business tables CHECKSUM-identical; units/roles/caps present; forced second migration + repair = identical state; post-upgrade admin changes (deleted user/role/service/unit, removed/denied caps, moved/renamed unit) survive another full update cycle.
- Unit handler 10/10 (membership, supervisor, multi-unit, inactive, flags, cycle guard, non-CRM user rejected, agent cannot save).
- Migration failure (table unavailable) → version NOT advanced, failure logged, lock released; next load retries → identical state.
- Static suite `after-sales-foundation-v08630.php` 41/41; `receipt-upload-runtime-v08600.php` 609/0; other suites unchanged vs baseline (remaining failures are historical version pins).
- `php -l` all PHP files; `node --check` all JS.

## Operational note
Accounts created by earlier versions may still use the old default password. The plugin must never set or test passwords, so **reset those accounts' passwords manually** (users with meta `bcrm_default_operations_staff` / `bcrm_seed_specialist`, and `svc_*_sup`, `agent_*`).
