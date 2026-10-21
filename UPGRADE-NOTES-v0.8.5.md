# Upgrade notes v0.8.5

Identity/assignment hardening release.

- Normal-agent customer visibility now uses the newest active assignment as the canonical owner, protecting legacy data with duplicate active assignments.
- Assignment writes lock the customer row (`FOR UPDATE`) before closing/recreating the active assignment, preventing concurrent double-assignment races.
- Collapsing an empty source profile no longer transfers active assignments to the target profile. Source active assignments are closed as `merged_closed`; target ownership is unchanged.
- A source profile is not auto-deleted while it still owns any phone numbers. This prevents a contaminated profile from detaching thousands of numbers during a merge.
- Adding a phone manually can no longer silently steal an existing phone from another customer, even for supervisors. Cross-profile movement must be explicit through the dedicated attach flow.
- Creating a manual invoice+phone bundle also refuses to silently move a phone already owned by another profile.
- Editing a manual phone now updates only manual invoice-phone rows for the same customer; imported/global invoice phone rows are never bulk-rewritten.
