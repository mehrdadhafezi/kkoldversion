# QA Report v0.8.59.9

## Scope

Deficiency to finance round-trip hardening.

## Verified

- Receipt upload controls remain repeatable and are not capped by plugin code at four rows.
- Scalar or malformed `case_documents` uploads are not treated as present for every receipt row.
- Duplicate receipt file signatures are skipped in the same request before upload persistence.
- `RETURNED_TO_DEFICIENCY` cases can be sent back to financial approval.
- Returned cases expose an in-case invoice registration action for follow-up deficiency work.
- Returned invoice registration refreshes `registered_invoice_amount` and `difference_amount`.

## Safety

- Code-only patch.
- No schema migration.
- No customer merge.
- No bulk invoice migration.
- No Guard change.
- No Run #7 continuation.
- No Run #8 implementation.

