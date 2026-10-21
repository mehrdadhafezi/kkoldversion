# Upgrade Notes v0.8.8

- Plugin header and database version move to `0.8.8`.
- Migration adds `customers.sales_manager` and `sales_manager_mapping`.
- Migration repairs missing complaint columns used by ownership and complaint queues.
- `bcrm_manage_customer_phones` protects the new phone detach action; administrators and CRM supervisors receive it automatically, while call-center agents remain configurable.
- Assignment updates an existing active assignment row when one already exists instead of creating a second active row.
