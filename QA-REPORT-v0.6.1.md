# QA Report — Biawin CRM Integration v0.6.1

## Critical fixes
- Fixed AJAX routing collision: hidden `action` / nonce fields from normal forms can no longer override the intended `wp_ajax_*` action.
- Added robust AJAX response parsing for `0`, `-1`, empty/HTML responses and HTTP errors.
- Added `contact_phone` directly to the interactions CREATE TABLE schema and retained runtime migration repair.
- Added repair checks for all interaction columns used by the current form so older/incomplete schemas can be repaired on upgrade.
- Interaction save is now an upsert for the current agent: repeated saves update the same interaction instead of creating duplicate call rows.
- Added controlled autosave for normal interaction fields; declared amount remains preview-only until explicit save or route/service action.
- Fixed race condition where edits made while an AJAX save was in flight could be incorrectly marked as saved.
- Added explicit DB error handling for interaction, complaint, journey, service status, service assignment, refund and financial status writes.
- Hardened phone+invoice attachment transaction: phone updates/inserts and invoice-phone links must all succeed or the transaction rolls back.
- Fixed complaint AJAX forms that carried an `admin-post` action hidden field.
- Financial reconciliation preview now calculates remaining service credit after already reserved/consumed service cases, not just gross credit.
- Added database repair control in CRM settings.

## Static QA executed
- PHP syntax lint on every PHP file: PASS
- JavaScript syntax check (`node --check`): PASS
- AJAX frontend action ↔ WordPress hook parity: PASS
- Hook callback existence scan: PASS
- Duplicate named JavaScript functions scan: PASS
- Interaction schema contains `contact_phone`: PASS
- Interaction runtime repair contains `contact_phone`: PASS
- Interaction backend supports create/update upsert: PASS

## Runtime verification still required on WordPress/MySQL
Static QA cannot replace a real server test. After upgrade, run these scenarios:
1. Open one assigned customer as an agent.
2. Change satisfaction/outcome and confirm the save badge reaches `ذخیره خودکار ✓`.
3. Enter declared amount and confirm preview happens without persistence requirement.
4. Press `ثبت تماس`; reopen profile and verify values remain.
5. Select no-request / complaint / service-request and verify route persists without refresh.
6. Create complaint and verify it appears immediately / on reopen.
7. Search a second phone, attach phone+invoice with one click, verify total and phone list update without refresh.
8. Select services and verify service cases plus remaining credit.
9. Update an assigned service case status.
10. Test financial and refund status updates from their cartables.
