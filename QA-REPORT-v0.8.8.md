# QA Report v0.8.8

Scope:
- Customer phone detach
- `bcrm_manage_customer_phones` permission guard
- Sales manager import and assignment mapping
- Duplicate active assignment protection
- Upgrade migration hardening

Verification:
- PHP lint passed for all plugin PHP files.
- JavaScript syntax check passed for `assets/frontend.js` and `assets/admin.js`.
- Static assertions passed for:
  - sales manager import storage on customer records
  - sales manager dropdown populated from imported customer/invoice values
  - sales manager mapping table and assignment resolver
  - update-in-place active assignment behavior
  - duplicate active assignment cleanup
  - customer phone detach AJAX/UI/service/audit path
- Confirmed the supervisor-only add/link restriction comes from the repeated-phone guard: a searched phone appearing on 10+ distinct invoices is treated as shared/high-risk.
- Normalizer smoke test passed for Arabic digits in money fields.

Environment note:
- Local PHP prints startup warnings for missing `xml`, `xmlreader`, and `xmlwriter` extensions. The plugin files still lint successfully.
