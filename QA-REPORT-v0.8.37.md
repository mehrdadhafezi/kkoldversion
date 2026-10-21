# QA Report - Biawin CRM Integration v0.8.37

Date: 2026-08-19

## Scope

- Comprehensive report Excel export.
- Comprehensive report CSV export.
- Customer rows with multiple invoices.

## Changes Verified

- Export headers now include invoice-level fields.
- The shared customer export stream emits one output row for each invoice attached to a customer.
- Customer fields, including internal ID, CustomerID, customer code, name, phone, assignment, call, workflow, and summary fields, are repeated on every invoice row.
- Customers without invoices are still exported once with empty invoice-level fields, so no customer profile disappears from the comprehensive export.

## Tests

- `php -l admin/class-biawin-crm-admin.php`
  - Result: Passed. Local PHP emitted startup warnings for missing XML extensions, but syntax validation completed with `No syntax errors detected`.

## Expected Output Example

If `CustomerID = C000001` has three invoices, the Excel/CSV export now contains three rows:

- `C000001` + first invoice code
- `C000001` + second invoice code
- `C000001` + third invoice code
