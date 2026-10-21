# QA Report - v0.8.17

Date: 2026-08-16
Environment: Local WordPress QA site (`bcrm_qa_0812`)
Plugin: `biawin-crm-integration`
Version tested: `0.8.17`

## Scope

- Assignment return by all agents.
- Assignment return by multiple selected agents.
- Assignment return by latest contact status.
- Assignment return by "not contacted yet".
- Assignment return by min/max total paid filters.
- Portal assignment form rendering and correct submit target.
- Normal assignment by selected agents with min/max total paid filters.

## Results

### Return Assignment - All Agents + Not Contacted + Paid Range

Input:
- Agents: all active agents
- Profile status: `pending`
- Customer status: `assigned`
- Contact status: not contacted yet
- Total paid: 100,000,000 to 350,000,000

Result:
- Matching uncontacted assigned profiles were returned.
- Profiles with `no_answer` or `success` contact statuses were not returned.
- Profiles below the minimum paid amount were not returned.

Status: PASS

### Return Assignment - Multiple Agents + Contact Status

Input:
- Agents: two selected agents
- Contact status: `no_answer`

Result:
- `no_answer` profiles assigned to the two selected agents were returned.
- `no_answer` profiles assigned to another agent were left assigned.

Status: PASS

### Return Assignment - Latest N

Input:
- One selected agent
- Contact status: not contacted yet
- Total paid: 180,000,000
- Limit mode: latest
- Limit: 1

Result:
- Only the latest matching assigned profile was returned.
- Older matching profile remained assigned.

Status: PASS

### Return Assignment - Legacy Contact Outcome Compatibility

Input:
- A fake interaction row with empty `outcome_key` and Persian outcome text `عدم پاسخ`.
- Return filter: `no_answer`.

Result:
- The profile was returned correctly by matching the legacy Persian outcome label.

Status: PASS

### Assignment - Selected Agents + Paid Range

Input:
- Two selected agents
- Explicit fake customer IDs
- Total paid: 100,000,000 to 200,000,000

Result:
- Three profiles inside the amount range were assigned.
- Profiles below 100,000,000 and above 200,000,000 stayed unassigned.
- No duplicate active assignment was created.

Status: PASS

### Portal Rendering

Checked output of the portal assignments view:

- Multi-select return agents field exists.
- "All active agents" option exists.
- Contact status filter exists.
- "Not contacted yet" option exists.
- Min/max paid return filters exist.
- Wrong hidden `page=biawin-crm-assignments` is not present.
- Correct hidden `bcrm_view=assignments` is present.

Status: PASS

## Notes

WP-CLI tests show redirect warnings after form handlers run. This is expected because the WordPress form handlers redirect after a successful operation.
