# QA Report — v0.8.65.1

## Scope
- Removed the visible case-level **«ثبت نتیجه نهایی مالی پرونده»** block from the finance review modal.
- Reworked the finance receipt review modal to follow the supplied HTML/screenshot structure.
- Each receipt is rendered as its own card with independent review state and detail form.
- Receipt card, previous/next navigation, main preview and bottom thumbnails are synchronized.
- Responsive stack switches at 640px.
- Kept real AJAX/data-frv-act per-receipt saving.
- Added invoice date to receipt-level `with_invoice` approval persistence/validation; refund `registered` intentionally has no invoice date.
- Admin-configured reject reasons / approve types / refund types remain sourced from existing financial settings.

## Checks
- All PHP files: `php -l` PASS.
- `assets/portal-fixes.js`: `node --check` PASS.
- No visible `decisionForm` / case-level final-result UI remains in `portal-fixes.js`.
- Existing `bcrm_fin_case_decide` backend endpoint was retained for backward compatibility but is no longer rendered/called by this modal.

## Version
`0.8.65.1`
