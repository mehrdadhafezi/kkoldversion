# QA Report - biawin-crm-integration v0.8.15

Date: 2026-08-13
Environment: Local WordPress QA (`bcrm_qa_0812`) via XAMPP MySQL + WP-CLI

## Scope

- Deficiency workflow final-submit form.
- Finance workflow final-submit form.
- Receipt and amount requirements before sending a deficiency case to finance.
- Finance approval with invoice or customer phone.
- Finance approval without invoice and notice text.
- Finance rejection path.
- Automatic completion after finance reconciliation.
- Automatic exclusion of discrepancies within +/- 500,000 toman.
- Timeline metadata formatting.
- Version and migration checks.

## Results

Passed:
- Plugin activates as v0.8.15 and stores DB version v0.8.15.
- `final_approved_amount` column exists on `wp_bcrm_deficiency_cases`.
- `biawin_crm_reconciliation_tolerance` is set to `500000`.
- Differences within +/- 500,000 toman do not create an open deficiency case.
- Sending a case to finance without a receipt is blocked with a validation error.
- Finance approval with invoice creates/uses the invoice data and auto-completes the case when reconciled.
- Finance approval without invoice auto-completes the case when `registered invoice total + approved amount` reconciles with the reported amount.
- Finance rejection returns the case to deficiency with only reason and description required.
- Deficiency agent corrected-declared-amount flow completes the profile when no discrepancy remains.
- Case detail page renders a unified `ثبت نهایی` form with calendar inputs.
- Timeline details render as readable key/value Persian labels instead of raw JSON.

## Commands

- PHP lint:
  - `php -l includes/class-biawin-crm-deficiency-workflow.php`
  - `php -l includes/class-biawin-crm-activator.php`
  - `php -l includes/class-biawin-crm-workflow-service.php`
  - `php -l biawin-crm-integration.php`
- WP-CLI activation/migration:
  - `wp plugin deactivate biawin-crm-integration`
  - `wp plugin activate biawin-crm-integration`
- WP-CLI workflow tests:
  - Synthetic deficiency/finance cases through `Biawin_CRM_Deficiency_Workflow::process_action()`.

## Notes

- Full browser upload was not executed from WP-CLI because WordPress only treats browser multipart uploads as real uploaded files. Backend validation and the UI file field are both in place.
