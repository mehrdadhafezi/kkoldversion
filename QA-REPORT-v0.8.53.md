# QA Report — v0.8.53

## Scope
Production Readiness + Blacklist Final Reconciliation + supervisor operational requests.

## Safety architecture
- Official `data/blacklisted-customer-ids.txt` remains a hard constraint.
- Readiness Guard writes only to `bcrm_readiness_quarantine` control metadata and plugin options/audit log.
- Guard does **not** rewrite CustomerID, Customer, Invoice, Phone ownership, Interaction, Merge, Payment, or declared_paid.
- Ordinary agents cannot open/edit quarantined profiles while Guard is enabled; managers/supervisors can inspect them.
- New assignment execution excludes quarantined profiles.
- Final Reconciliation CSV is read-only and derives candidate destination evidence from the existing historical Replacement Manifest.
- A blacklisted live row with no deterministic manifest evidence is emitted as `CURRENT_BLACKLISTED_NOT_IN_MANIFEST / MANUAL_REVIEW` and is never auto-repaired.

## Supervisor requests
1. Assignment screen now shows live queue counts per active agent: active, readiness-quarantined, and currently workable.
2. Agents can add a mobile number directly to an existing invoice. The operation never steals a phone from another customer; conflicts are blocked. Audit evidence stores actor, timestamp, invoice, customer, before/after invoice phones, and source `manual_invoice_phone`.
3. Customer invoice panel explicitly shows the live sum of attached invoices; customer totals continue to be recalculated from actual invoices excluding `exclude_from_total=1` rows.

## Static QA
- All PHP files: `php -l` PASS.
- All JS assets: `node --check` PASS.
- Plugin header/constants: 0.8.53.

## Required production procedure
1. Install/replace plugin with v0.8.53.
2. Open Historical Identity Audit.
3. Run `Readiness Audit (read-only)`.
4. Verify current Blacklist counts.
5. Click `Activate/Sync Readiness Guard` before agents resume work.
6. Download `Final Reconciliation CSV` and review/freeze it before any future identity-rekey commit engine.

## Important non-goal
v0.8.53 does not bulk rekey the remaining blacklisted CustomerIDs. It makes Production operationally safe for agents while producing the final reconciliation evidence needed for a separately frozen, journaled identity commit. This is intentional to avoid irreversible bulk identity writes before the final mapping is inspected.
