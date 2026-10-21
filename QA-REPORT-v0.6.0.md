# QA Report 0.6.0

## Static checks
- PHP syntax check: all PHP files pass `php -l`.
- JavaScript syntax check: `frontend.js` passes `node --check`.
- Version bumped to `0.6.0`.
- AJAX endpoints added for reconciliation preview, interaction save, journey selection, service selection, service status, complaint creation, manual bundle and invoice+phone attach.
- Old standalone phone/invoice attach endpoints remain blocked by design.

## Core business rules verified in code
- Invoice code remains unique.
- Invoice + phone attach is transactional.
- Search-number can be reused automatically during attach; no second phone entry required.
- Profile completeness is based on declared amount vs invoice total with configured tolerance.
- Incomplete profiles create/update financial verification case.
- Only complete profiles can enter no-request / complaint / service-request journey.
- Service credit calculation remains multiplier 2.5, floor 200M, cap 1.5B (configurable in settings).
- Service allocation enforces remaining credit server-side under a DB transaction.
- Service cases remain independent per selected service.
- Portal user authentication/RBAC from 0.5.0 remains intact.

## Runtime limitation
A real WordPress + MySQL browser session is still required for final end-to-end acceptance because this build environment does not include the production WordPress database and user/session state.
