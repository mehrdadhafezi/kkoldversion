# Biawin CRM v0.8.56.0
# Release B.1 Security Scope

B.1 does not create a parallel authorization system. The adapter accepts an explicit scope context and defaults to existing WordPress capabilities when available.

## Roles

Supported scope roles are `admin`, `manager`, `agent`, `finance`, `deficiency`, `service`, and `auditor`.

## Rules

- Manager scope remains manager scope.
- Ordinary agent scope cannot retrieve manager-wide canonical population, assignment, event, or case lists.
- Finance scope may use finance adapter methods but does not gain unrelated case/customer-wide reporting.
- Deficiency scope may use case context but does not gain finance/customer-wide reporting.
- Service scope may use case context but does not broaden outside service/case-domain reporting.
- Auditor/admin technical access follows existing capabilities and must still be passed explicitly by future consumers.

## B.1 Test Coverage

`tests/reporting-consumer-adapter-b1.php` executes FX-SEC-001 equivalents for manager, agent, finance, deficiency, and service scope.

## No Domain Mutation

The adapter does not call customer save/update, assignment mutation, case mutation, Guard mutation, blacklist mutation, migration/rekey routines, Run #7 continuation, or Run #8 endpoints.

