# Upgrade Notes - v0.8.23

- Adds an admin cleanup page for uploading CustomerID lists and reviewing/removing financially unapproved invoices.
- Adds invoice columns for excluding an invoice from customer totals without deleting the invoice record.
- Customer total recalculation now ignores invoices marked as excluded from totals.
- Run a preview first on production, then re-upload with execution enabled only after the counts look correct.
