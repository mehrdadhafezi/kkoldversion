# QA / Hardening Report — Biawin CRM v0.7.7

## Scope
Full static audit of v0.7.6 and hardening of the operational paths used by portal admins, supervisors, specialists and agents.

## Fixed in v0.7.7
- Interaction persistence now treats declared amount as preview-only during ordinary auto-save. It is persisted only on explicit contact save or a route/service action that needs reconciliation.
- Interaction save now fails visibly if profile recalculation or customer status update fails after the interaction row is written.
- Customer profile recalculation and workflow synchronization now return success/failure instead of silently ignoring database errors.
- Financial-case creation/update now reports persistence failure to the caller.
- Financial evidence upload now verifies the attachment-row INSERT; if DB persistence fails, the uploaded WordPress attachment is removed to avoid orphaned files.
- Phone verification now checks the primary-phone reset write before continuing.
- Legacy add-phone UPSERT now checks the database result.
- Customer-code generation now retries unique-code collisions caused by concurrent requests.
- FIN / SRV / REF case-code creation now retries collisions, reducing concurrency failures with multiple simultaneous operators.
- Service selection now verifies the customer journey update before COMMIT.
- Service-unit save is transactional: unit, supervisor and members either save together or roll back together.
- Service supervisors now see open/unassigned cases for their supervised units and can distribute them to active unit agents.
- Financial, complaint and refund queues now use role-aware data scopes. Agents cannot update another agent's owned case by posting a forged ID; supervisors keep unit-wide queue access.
- Complaint resolution now checks ownership/supervisor permission and uses SELECT ... FOR UPDATE to prevent double-resolution races.
- Customer profile access and customer-list access scopes are aligned for financial/complaint/refund specialist roles.
- User toggle/password handlers now verify that the target is a CRM portal user.
- User-facing wording was normalized from “complaint” to “dissatisfaction / after-sales” while internal stable keys remain unchanged for backward compatibility.

## Automated checks passed
- PHP syntax: all 11 PHP files passed `php -l`.
- JavaScript syntax: `assets/frontend.js` and `assets/admin.js` passed `node --check`.
- Hook/callback audit: no registered PHP callback points to a missing method.
- REST/AJAX registration audit: all direct JavaScript AJAX actions are registered server-side.
- Database reference audit: every `Biawin_CRM_DB::table()` reference maps to a table created by the activator schema.
- Duplicate static-method scan: none found.
- Phone normalization tests passed for 09, 9xxxxxxxxx, +98, 0098, Persian digits and Excel scientific notation.
- Money normalization tests passed for Persian and Latin thousands separators.

## Important runtime boundary
This environment does not run the user's exact WordPress + MySQL + installed-plugin stack, therefore end-to-end browser/runtime confirmation on the production/staging database cannot be truthfully guaranteed here. Before production rollout, run the staging acceptance flow below.

## Required staging acceptance flow
1. Login as portal admin/supervisor and as one ordinary call agent.
2. Confirm agent dashboard/customer list only shows active assigned profiles.
3. Open an assigned customer; change satisfaction/outcome and confirm auto-save survives reload.
4. Type declared amount and confirm live reconciliation does not persist it by itself; explicitly save and confirm it survives reload.
5. Select no-request / dissatisfaction / service request and confirm the route persists without refresh.
6. Create dissatisfaction, resolve it once, and verify a second concurrent/duplicate resolution is blocked.
7. Select services above available credit and confirm negative remaining credit is allowed and service cases are created.
8. Login as service supervisor, see unassigned cases in the supervised unit, assign one to a unit agent, then login as that agent and update status.
9. Login as financial agent, claim/update an unassigned financial case, then verify another financial agent cannot mutate it by direct POST.
10. Upload a payment evidence file and verify both media attachment and CRM attachment row exist.
11. Login as refund agent/supervisor and verify queue ownership rules.
12. Run Excel import header detection, staging batches, preview and commit against the production-format sample file.
13. Verify CustomerID grouping, prepayment-unapproved tags and 1404 cohort classification.
14. Verify customer search, pagination, per-page selection and profile navigation.
15. Export the management XLSX report and verify first assignment / first assignee / latest activity fields.
16. Run two-browser concurrent tests for service creation / customer-code generation to verify collision retry behavior.
