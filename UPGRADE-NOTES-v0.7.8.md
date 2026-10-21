# v0.7.8

- Added bulk and single-profile assignment return.
- Returning closes active assignment rows with status `returned`, returns the customer to the unassigned queue, and records audit/activity history.
- Dashboard cache is invalidated immediately after assign/return actions.
