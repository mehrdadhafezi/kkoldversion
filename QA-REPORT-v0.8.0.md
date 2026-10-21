# QA Report v0.8.0

Static checks:
- PHP syntax: all plugin PHP files
- JavaScript syntax: frontend/admin assets
- Service selection no longer gated by profile completeness in UI/JS/backend
- Incomplete reconciliation continues to create/keep financial case via sync_customer
- Contact outcome filters are server-side and scoped to current user access
- Pagination/search preserve contact outcome filter
