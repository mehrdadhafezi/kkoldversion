# QA Report v0.8.41

Focus: read-only reconciliation with the official CustomerID blacklist as a hard constraint.

- Bundles 48,331 permanently invalid CustomerIDs from `customerID هایی که باید حذف شن.xlsx`.
- No blacklisted CustomerID is accepted as a valid historical reconstruction target.
- Reports how many blacklisted IDs still exist in the current CRM and counts their related invoices/phones/interactions/assignments/cases/etc.
- Flags corrected-workbook destinations that collide with the blacklist as `BLACKLISTED_TARGET` and requires a new valid CustomerID.
- Does not mutate CRM domain tables.
- Bulk ownership correction and cleanup remain safety-locked by default.
