# QA v0.7.2

Scenarios covered by code review:
- Global header search submits to `bcrm_view=customers`.
- Customers-page search explicitly submits to portal URL and preserves customers view.
- Customer list uses SQL `COUNT(DISTINCT c.id)` + `LIMIT/OFFSET`; no bulk-load of all profiles.
- Per-page values are allow-listed to 25/50/100/200.
- Page bounds are clamped safely.
- Non-admin row-level access predicates are reused for count and list queries.
- Search includes display name, customer code, source CustomerID, normalized phone and invoice code.
