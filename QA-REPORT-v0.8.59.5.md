# QA Report v0.8.59.5

## Scope

Deficiency Portal UI Polish and Font Selection.

## Changes Verified

- Receipt rows in the deficiency send-to-finance form use a full-width repeatable list with compact add/remove buttons.
- The `+ افزودن فیش` action appends another amount/file row without stretching into a large form panel.
- A global portal font setting is available in CRM settings.
- The portal header exposes a per-user font selector.
- Selected font stacks are applied to portal shells, embedded admin pages, inputs, selects, textareas, and buttons.

## Safety

- No database schema migration.
- No customer mutation.
- No deficiency case mutation.
- No assignment mutation.
- No Guard change.
- No Run #7 continuation.
- No Run #8 implementation.

## Validation Plan

- PHP lint for changed PHP files.
- Targeted static regression test for font selection and receipt UI selectors.
- Full PHP test suite.
- Fresh ZIP extract package audit.
