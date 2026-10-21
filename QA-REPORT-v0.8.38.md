# QA Report - Biawin CRM Integration v0.8.38

Date: 2026-08-19

## Scope

- Invoice ownership correction after the large corrective workbook.
- Customer names and phone ownership after correction.
- Search and call-result phone selection reliability.

## Data Audit Performed

Source files checked:

- `bcrm-backup-20260819-132149-manual.ndjson`
- `خروجی_نهایی_مشتریان_اصلاح_شده.xlsx`

Key findings:

- Corrective workbook contained 98,147 invoice rows and 69,368 unique CustomerID values.
- The backup contained 98,149 invoices and 27,861 customer profiles.
- Audit log contained 98,145 `invoice_ownership_corrected` events.
- For checked moved rows, many target profiles had phone sets that did not overlap the invoice phones from the corrective workbook. This explains search opening the wrong-looking customer and call forms missing selectable phones.

Representative case:

- Corrective workbook maps invoice `1135777` to `C001650` with `حسین قاسمی / 09175003250`.
- The live backup had `C001650` as `محمد شمسی گوگوش / 09106864006`, then invoice `1135777` was attached to it.
- The same workbook maps invoices `1414930` and `1416859` with `09106864006` to `C060368`.

## Fix Verified

- Ownership correction now calls an identity repair step for every affected customer, including rows that are already attached to the target customer.
- The repair step rebuilds `display_name`, `normalized_name`, `sales_manager`, and customer phone rows from the invoices currently owned by the profile.
- Phone rows are reassigned to the corrected customer when invoice phones show the corrected ownership.

## Tests

- `php -l admin/class-biawin-crm-admin.php`
  - Result: Passed. Local PHP emitted startup warnings for missing XML extensions, but syntax validation completed with `No syntax errors detected`.

## Required QA Flow

1. Restore the supplied backup on a local test site.
2. Install v0.8.38.
3. Run `اصلاح مالکیت فاکتورها` with the corrective workbook again.
4. Continue batches until the job finishes.
5. Search `09106864006` and verify it opens the profile owning invoices `1414930` and `1416859`.
6. Search/open invoice `1135777` and verify it belongs to `C001650` with phone `09175003250`.
