# QA Report — Biawin CRM v0.8.65.11

## Scope
Added a read-only **Finance Approval Report** to wp-admin.

## Behavior
- New submenu: `CRM بیاوین → گزارش تأیید مالی`.
- Source population: deficiency cases with non-null `sent_to_finance_at`.
- Receipt source: `attachments` where `object_type=deficiency_case` and `file_kind=bank_receipt`.
- Review source: `receipt_reviews`.
- Invoice codes come from Finance review `details_json` (`invoice_number` / `invoice_links`; refund rows also understand `refund_links`).
- Filters: sent-to-finance date, review date, receipt status, review type, finance agent, reviewer, and text search including invoice number.
- Tabs: profile summary, receipt details, approved invoices, rejected receipts, other statuses.
- XLSX export respects the same filters and contains 5 sheets.
- Reporting is read-only and does not execute workflow transitions or mutate CRM data.

## Validation
Run `php -l` on all modified/new PHP files, then ZIP integrity test.
