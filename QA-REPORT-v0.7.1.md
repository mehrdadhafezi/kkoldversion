# QA v0.7.1

Static checks completed:
- PHP syntax check: all PHP files passed.
- JavaScript syntax check: frontend.js and admin.js passed `node --check`.
- Front route map includes every portal/sidebar view.
- POST forms rendered from admin pages receive `bcrm_portal_context=1`.
- Redirect filter translates plugin admin destinations back to front `bcrm_view` URLs when the request originated in portal context.
- WordPress admin-bar filter targets the configured portal page/permalink.
- Sidebar collapse state persists in browser storage and mobile behavior remains independent.

Runtime WordPress/browser QA should test each sidebar item and a save/submit action inside every management view after upgrade.
