# QA Report - v0.8.18

Date: 2026-08-16
Environment: Local WordPress QA site (`bcrm_qa_0812`)
Plugin: `biawin-crm-integration`
Version tested: `0.8.18`

## Issue

Manual assignment displayed a success message, but selected profiles did not appear under the selected agents. The root cause was the sales manager rule priority checkbox being enabled by default. When a matching sales manager rule existed, it silently overrode the manually selected agents.

## Fix

- Sales manager rule priority is no longer checked by default.
- If manual agents are selected, assignment now respects those selected agents.
- Sales manager rules are applied only when no manual agent is selected or when the user chooses distribution across all active agents.

## QA Results

### Manual Assignment With Sales Manager Rule Also Checked

Input:
- Two fake customers with the same sales manager.
- Sales manager rule mapped that sales manager to Agent 13.
- Manual assignment selected Agent 11 and Agent 12.
- The sales manager rule priority checkbox was intentionally posted as enabled.

Result:
- Customer 1 assigned to Agent 11.
- Customer 2 assigned to Agent 12.
- Agent 13 did not receive either profile.

Status: PASS

### Agent Visibility

Result:
- Agent 11 could see only Customer 1.
- Agent 12 could see only Customer 2.
- Agent 13 could not see either manually assigned customer.

Status: PASS

### Portal Form Rendering

Result:
- Manual agent multi-select exists.
- Sales manager rule priority checkbox exists.
- Sales manager rule priority checkbox is not checked by default.

Status: PASS

### Syntax Check

Result:
- PHP syntax check passed.

Status: PASS

## Notes

WP-CLI tests show redirect warnings after successful form submission. This is expected for WordPress admin-post handlers.
