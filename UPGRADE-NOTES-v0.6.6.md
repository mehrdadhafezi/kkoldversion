# Biawin CRM Integration v0.6.6 — Upgrade Notes

## CustomerID-first profile identity
- Added `customers.source_customer_id` as the primary source identity when the imported file maps `CustomerID`.
- Added `customer_external_id` to import mapping and automatic aliases for `CustomerID`, `customer_id`, `customer id`, and Persian equivalents.
- All rows/invoices with the same mapped CustomerID now converge into one customer profile.
- Phone matching is used only as fallback when CustomerID is missing.
- Re-importing data previously grouped by phone will move invoices into CustomerID profiles and automatically collapse source profiles after they become invoice-empty.
- Import preview now reports distinct CustomerID count.

## Empty profile collapse after merge
- Moving/attaching invoices and their phones to another profile now checks the source profile after the move.
- If the source has zero invoices, its customer-level records/phones are moved to the target, the empty profile is deleted, and profile count is recalculated.
- Merge event/audit trail is retained.

## Service credit over-allocation
- The computed service credit remains visible as the customer's entitlement/reference amount.
- Agents may select services above the remaining credit.
- Remaining credit can become negative and is presented as a warning, not a blocker.

## Customer ID / autosave AJAX fix
- Fast portal AJAX interaction save now sets both REST URL `id` and body `customer_id` explicitly.
- Journey selection and service selection use the profile-level customer ID, rather than relying on form serialization.
- Autosave surfaces backend error messages instead of failing silently.

## Service units and cartables
- Added service unit tables and admin UI to define unit, supervisor, and member agents.
- Added role `bcrm_service_supervisor` (Service Unit Supervisor), which has portal/service access without global CRM-manager access.
- Unit supervisors can see open service cases for their own units and assign them only to agents who are members of that same unit.
- Service cases keep their service `unit_key`, allowing the cartable to be routed by the configured destination unit.

## Phone verification semantics
- Imported/linked phones start as `unverified`.
- A phone becomes `verified` only when an authorized agent explicitly confirms it after talking to the customer/validating the number.
- `is_primary` is separate from verification status. Making a number primary no longer automatically verifies it.
- The portal allows Verify / Unverify / Make primary without page refresh.
