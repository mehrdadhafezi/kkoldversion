# QA Report — v0.8.53.3.1

## Purpose
MariaDB schema-compatibility hotfix only. No change to Frozen Plan mapping or domain migration semantics.

## Root cause
`dbDelta()` parsed the one-line CREATE TABLE definitions as a single field definition when the tables already existed and generated malformed `ALTER TABLE ... CHANGE COLUMN` statements.

## Fix
- No `dbDelta()` for the two commit-engine control tables.
- Existing tables are left untouched.
- Missing tables are created with explicit multi-line `CREATE TABLE`.
- Required columns are checked fail-closed before run/journal use.

## Safety
- Frozen Plan SHA256 remains `bb87ff58b41a619203aa805bc9e557c887decfdea8888290f7906ebcf9191048`.
- Guard behavior unchanged.
- 228 MANUAL_REVIEW rows remain excluded from auto commit.
- This hotfix does not itself run a migration.
