# QA Report — v0.8.53.1

Scope: Canonical Final Reconciliation Plan only.

Safety contract:
- Read-only planner/export.
- No CustomerID/domain/history/guard writes.
- Exactly one canonical output row per current blacklisted internal customer.
- A row that is both historical source and corrected target is represented as one COMPOSITE decision; neither role is discarded.
- Manual/ambiguous source evidence blocks auto-resolution.
- Final IDs may not be blacklisted.
- Existing-target decisions must point outside the current blacklisted set.
- Incompatible duplicate final-ID claims block invariants.

Static QA is run during packaging with PHP lint on all PHP files and node --check on JS assets.
