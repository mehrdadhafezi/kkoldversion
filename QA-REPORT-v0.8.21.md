# QA Report - v0.8.21

Date: 2026-08-16
Environment: Local WordPress QA site (`bcrm_qa_0812`)
Plugin: `biawin-crm-integration`
Version tested: `0.8.21`

## Issue

Users could receive "No profiles found for the selected conditions" during assignment without knowing whether any assignable profiles remained. Direct single-customer assignment could also be blocked by the default contact-status filter.

## Fix

- Added filter counters to the assignment page:
  - Matching current filters.
  - Assignable with the current option.
  - Already assigned.
  - Visible rows in the table.
- Empty assignment results now return to the assignment page with an actionable warning instead of stopping on a WordPress error page.
- Direct single-customer assignment no longer applies the default "not contacted yet" filter unless a contact filter is explicitly posted.

## QA Results

### Assignment Counters Render

Input:
- Minimum total paid: 20,000,000.
- Invoice count: exactly 1.
- Contact status: not contacted yet.

Result:
- The page rendered counters for matching profiles, assignable profiles, already assigned profiles, and visible table rows.

Status: PASS

### Empty Result Handling

Input:
- Impossible high minimum total paid.
- Invoice count: exactly 1.
- Contact status: not contacted yet.

Result:
- The handler redirected back to the assignment page.
- A clear warning message was shown.
- Filter counters were still visible.

Status: PASS

### Direct Assignment Of Contacted Customer

Input:
- One fake customer with a previous `success` call interaction.
- Direct single-customer assignment with no contact filter posted.

Result:
- The contacted customer was assigned successfully to the selected agent.

Status: PASS

### Syntax Check

Result:
- PHP syntax check passed.

Status: PASS
