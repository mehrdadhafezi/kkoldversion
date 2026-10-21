# Biawin CRM v0.8.6

- Enforces exactly one active (`assigned`) owner per customer at the database level using a generated-column UNIQUE guard.
- Repairs legacy duplicate active assignments during upgrade. The keeper is selected by latest real agent activity, then activity count, then assignment time/id; all other rows are preserved as `duplicate_closed`.
- Assignment writes are transactional, row-locked and idempotent when the same owner is submitted again.
- Frontend ownership checks use the canonical active assignment, preventing a stale duplicate row from granting access.
- Existing assignment history is never deleted. Repair actions are written to audit/activity history.
