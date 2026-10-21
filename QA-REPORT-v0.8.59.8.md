# QA Report v0.8.59.8

## Scope

Deficiency workflow finance handoff with uploaded receipt files.

## Changes Verified

- Finance handoff remains labeled `ارسال به تایید مالی`.
- `ارسال فیش` still opens receipt upload controls.
- Backend now treats uploaded receipt files or positive receipt amounts as receipt handoff intent.
- Successful receipt upload path continues to `FINANCE_REVIEW_PENDING`.
- Repeatable `case_documents[]` file indexes are handled defensively.
- Each submitted receipt row requires both amount and file.

## Safety

- Code-only patch.
- No schema migration.
- No customer data mutation outside the submitted workflow action.
- No invoice mutation.
- No Guard change.
- No Run #7 continuation.
- No Run #8 implementation.

