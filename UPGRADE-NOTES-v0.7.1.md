# Biawin CRM v0.7.1

- Fixed front portal route preservation for embedded wp-admin management screens.
- Added explicit portal context marker to POST forms so redirects return to the originating front portal view.
- Added fallback mapping from legacy `page=biawin-crm-*` query strings to `bcrm_view` routes.
- Hidden the WordPress admin toolbar on the CRM portal page for every logged-in portal user, including administrators viewing the front portal.
- CRM operational users are redirected away from wp-admin; WordPress administrators retain wp-admin access when they intentionally open it.
- Added desktop collapsible sidebar with persisted state (localStorage), mobile overlay, and hamburger controls.
- Applied the modern portal design system to all embedded management pages: cards, forms, tables, tabs, notices, filters, inputs, buttons and responsive states.
