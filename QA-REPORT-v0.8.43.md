# QA Report — v0.8.43 Replacement ID Allocation + Migration Manifest

## Scope
Read-only migration manifest layered on top of v0.8.42. No CRM domain table writes are implemented.

## Allocation rules
- Blacklist is a permanent hard constraint.
- Existing current CRM CustomerIDs are reserved.
- Historical baseline old IDs are reserved.
- All corrected-target IDs are reserved, including absent targets.
- Import-stage source CustomerIDs are reserved.
- Replacement IDs are allocated only for corrected targets that are themselves blacklisted.
- Multiple historical profiles mapping to one blacklisted corrected target share one replacement ID.
- A blacklisted current source row does **not** by itself require a new ID when its corrected target is valid.
- Allocation is deterministic for a given database/baseline state: ascending blacklisted corrected-target order from baseline discovery, sequential free IDs above the maximum reserved number.

## Manifest strategies
- USE_EXISTING_VALID_TARGET
- CREATE_VALID_TARGET
- REKEY_EXISTING_BLACKLISTED_TARGET
- CREATE_REPLACEMENT_TARGET
- MANUAL_REVIEW

## Safety
- No INSERT/UPDATE/DELETE in the manifest class.
- Existing v0.8.42 maintenance lock remains enabled by default.
- Recent post-cutoff source activity is flagged as CONFLICT_CURRENT_ACTIVITY.
- Recent activity on the destination is reported and must be preserved in the eventual commit implementation.
- Full CSV manifest export is generated read-only from live state.

## Static QA
- PHP syntax lint: required for all plugin PHP files.
- JS syntax check: admin.js and frontend.js.
