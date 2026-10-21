# QA Report — v0.8.65.2

## Scope
- Explicit invoice-only vs invoice+selected-phone attachment.
- Receipt-driven finance completion / removal from active finance workload.
- Finance with-invoice field reduction and Jalali date round-trip.
- Satisfaction interaction phone requirement and declared-amount gate.
- Deficiency-agent receipt upload permission.
- Server-side receipt-required and receipt-total consistency guards.
- Idempotent service request retry behavior.
- INCOMPLETE_NEW stale-state handling for assigned deficiency agents.

## Static verification
- PHP syntax: all plugin PHP files pass `php -l`.
- JavaScript syntax: `assets/frontend.js` and `assets/portal-fixes.js` pass `node --check`.
- Removed finance modal fields: no `data-frv-invoice-date` or `data-frv-invoice-amount` remain for approve-with-invoice.
- Legacy visible case-level finance final form removed from the current finance detail UI; legacy backend endpoint remains only for backward compatibility.

## Important production verification
Customer IDs C0654718 and C068045 were not queried against production DB in this package-only QA. The patch adds backend guards that prevent the reported classes of problems going forward. Existing historical rows should be audited on the live DB after deployment.
