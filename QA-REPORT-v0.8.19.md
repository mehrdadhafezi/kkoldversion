# QA Report - v0.8.19

Date: 2026-08-16
Environment: Local WordPress QA site (`bcrm_qa_0812`)
Plugin: `biawin-crm-integration`
Version tested: `0.8.19`

## Issue

Assignment could look successful while the selected profiles were hard to trace. The agent selector was an ambiguous multi-select control, and the page did not show which customers were assigned to which agents after a batch assignment.

## Fix

- Replaced the assignment multi-select agent control with explicit agent checkboxes.
- Added a post-assignment detail notice showing customer-to-agent destinations for the latest batch.
- Added a "Latest Active Assignments" table to the assignment page to trace previous batches.

## QA Results

### Screenshot-Like Filter Scenario

Input:
- Minimum total paid: 20,000,000
- Maximum total paid: empty
- Minimum invoice count: 1
- Maximum invoice count: 1
- Assignment count: 10
- Only unassigned: enabled
- Manual agent: one checked agent
- Sales manager rule priority: disabled

Result:
- 10 matching profiles were assigned.
- The maximum total paid field was not required.
- The resulting batch was stored and shown in the latest assignment details.
- The latest active assignment table showed the assigned customers and destination agent.

Status: PASS

### Agent Visibility

Result:
- A customer assigned in the batch was visible in the selected agent's customer portal.

Status: PASS

### Portal Rendering

Result:
- Agent selection renders as checkboxes.
- The old assignment multi-select is no longer rendered.
- Latest assignment details are visible after assignment.
- Latest active assignments table is rendered.

Status: PASS

### Syntax Check

Result:
- PHP syntax check passed.

Status: PASS

## Notes

If no rows are manually selected in the customer table, assignment chooses the highest total-paid matching profiles from the current filters. This can assign profiles that are not visible in the first screen area, so the new latest-assignment detail block is used to trace the exact destination.
