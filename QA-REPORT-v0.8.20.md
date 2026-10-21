# QA Report - v0.8.20

Date: 2026-08-16
Environment: Local WordPress QA site (`bcrm_qa_0812`)
Plugin: `biawin-crm-integration`
Version tested: `0.8.20`

## Issue

Fresh assignment batches could include customers who already had call interactions, because the assignment filters did not include contact status. The page also did not show the latest contact status or who recorded the call, making it unclear whether a customer had already been contacted and by whom.

## Fix

- Added assignment contact-status filter.
- Default assignment filter is now `تماس گرفته نشده`.
- The assignment save handler enforces the contact filter.
- Assignment candidate table now shows latest contact status and the user who recorded it.
- Latest assignment details now show whether the customer had a previous contact.
- Latest active assignments table now shows latest contact status and contact recorder.

## QA Results

### Default Fresh Assignment Excludes Contacted Customers

Input:
- Two selected fake customers.
- Customer A had no interaction rows.
- Customer B had a previous `success` call interaction.
- Contact filter: `تماس گرفته نشده`.
- Same paid amount and invoice-count filters for both.

Result:
- Customer A was assigned.
- Customer B stayed unassigned.
- Latest batch details showed Customer A as having no registered contact.

Status: PASS

### Portal Rendering

Result:
- Assignment contact-status filter is visible.
- `تماس گرفته نشده` is selected by default.
- Candidate table shows latest contact status.
- Candidate table shows contact recorder.
- Latest assignment details include contact status context.

Status: PASS

### Syntax Check

Result:
- PHP syntax check passed.

Status: PASS

## Notes

The Chrome plugin connection failed in this session before browser inspection, but the assignment behavior was validated directly through the WordPress QA database and portal rendering.
