# QA report v0.8.5

Focus: identity linking, phone ownership and assignment isolation.

Static checks:
- PHP syntax checked for all PHP files.
- JavaScript syntax checked with node --check.
- ZIP integrity checked.

Reviewed flows:
- Exact phone search -> invoice candidates
- Explicit phone + invoice attach
- Manual phone-only creation
- Manual invoice + phone creation
- Manual phone edit
- Empty-profile collapse after merge
- Bulk/direct assignment
- Agent customer-access scope

Important runtime diagnostic for existing duplicate assignments:
SELECT a.id,a.customer_id,a.agent_user_id,u.display_name,a.status,a.assigned_at,a.completed_at
FROM wp_bcrm_assignments a LEFT JOIN wp_users u ON u.ID=a.agent_user_id
WHERE a.customer_id=? ORDER BY a.id ASC;
