# QA Report v0.8.6

Focus: duplicate-assignment hardening.

Checks:
- PHP syntax for all PHP files.
- JS syntax for all JS assets.
- Upgrade migration closes legacy duplicate active assignments without deleting history.
- Database guard prevents a second active assignment for the same customer even if an application bug/race attempts it.
- Repeated assignment to the already-current agent is idempotent.
- Customer access resolves against the canonical active assignment.

Post-upgrade verification SQL:
```sql
SELECT customer_id, COUNT(*) c FROM wp_bcrm_assignments WHERE status='assigned' GROUP BY customer_id HAVING COUNT(*) > 1;
```
Expected: zero rows.
