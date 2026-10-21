# QA Report v0.8.59.7

## Scope

Deficiency finance handoff options.

## Changes Verified

- The deficiency workflow option is now labeled `ارسال به تایید مالی`.
- Receipt uploads are controlled by an `ارسال فیش` checkbox.
- Receipt amount/file rows remain hidden until `ارسال فیش` is selected.
- Finance handoff no longer requires a receipt unless `ارسال فیش` is selected.
- `وضعیت عودت: عودت شده` is stored as handoff metadata and a customer tag.
- Refund status is visible in timeline metadata, report KPI, CSV export, and REST export.

## Safety

- No database schema migration.
- No customer mutation beyond existing workflow tags.
- No invoice mutation.
- No Guard change.
- No Run #7 continuation.
- No Run #8 implementation.

## Validation

- Targeted static workflow test.
- JavaScript syntax check.
- PHP lint.
- Full PHP test suite.
- Fresh ZIP extract package audit.
