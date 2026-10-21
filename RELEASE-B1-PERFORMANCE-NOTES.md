# Biawin CRM v0.8.56.0
# Release B.1 Performance Notes

B.1 is infrastructure for future dashboards, paged lists, CSV, and Excel exports. It avoids public APIs that require one query per customer.

## Batch Paths

- `financial_snapshot_for_customers()` processes IDs in bounded chunks of 500.
- `newest_active_assignments_for_customers()` processes IDs in bounded chunks of 500.
- `case_guard_summary()` processes Guarded customer IDs in bounded chunks of 500.
- Population service memoization is reused through the adapter.

## Request-Scoped Memoization

The adapter uses static request-lifetime memory only. It does not create transients, database cache tables, filesystem cache, or cross-request authoritative truth.

Cached failures remain failures. Unknown is never converted to zero.

## Future Optimization Notes

No schema or index changes are included in B.1. If later release audits find slow case/customer validity joins or event detail pagination under production volume, indexes should be proposed in a separate schema-bearing release contract.

