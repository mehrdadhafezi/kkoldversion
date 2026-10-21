# Package Hygiene v0.8.59.4

## Package

`biawin-crm-integration-v0.8.59.4-deficiency-portal-workflow-fixes.zip`

## Scope

v0.8.59.4 is a focused Deficiency Portal Workflow patch for user deletion, portal search routing, supervisor assignment return, multi-receipt finance handoff, and KPI help semantics.

## Excluded

- `.git`
- temp extraction directories
- database dumps
- production exports
- backup NDJSON files
- credentials or secrets

## Safety Statement

This release changes UI/control flow and role-safe user deletion only. It does not migrate domain data, rebuild caches, modify Guard state, continue Run #7, or implement Run #8.
