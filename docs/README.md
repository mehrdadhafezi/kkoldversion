# Biawin CRM Integration — Architecture Docs

Current architecture baseline for **v0.8.65.17**:

- [`../STRUCTURE.md`](../STRUCTURE.md) — high-level plugin structure, source-of-truth boundaries, architectural risks, change-safety rules.
- [`DEFICIENCY-FINANCE-STATE-MACHINE.md`](DEFICIENCY-FINANCE-STATE-MACHINE.md) — detailed **current/code-inferred** Deficiency + Finance state machine, actions, permissions, assignment rules, receipt-review finalization, legacy path, tab mapping, and known inconsistencies.
- [`OWNERSHIP-RECONCILIATION-ARCHITECTURE-CONTRACT.md`](OWNERSHIP-RECONCILIATION-ARCHITECTURE-CONTRACT.md) — **proposed / not yet implemented** architecture and business contract for invoice/phone ownership locks, claims, review, evidence, OTP, transfer plans, holds, audit, and post-transfer Deficiency effects.

`DEFICIENCY-FINANCE-STATE-MACHINE.md` describes current behavior inferred from source. `OWNERSHIP-RECONCILIATION-ARCHITECTURE-CONTRACT.md` describes approved/proposed future behavior and must not be read as runtime proof.

- `OWNERSHIP-RECONCILIATION-IMPLEMENTATION-DESIGN.md` — frozen schema/migration/class design for the approved Ownership Reconciliation domain; no runtime implementation yet.
