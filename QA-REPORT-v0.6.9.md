# QA Report v0.6.9

Static checks performed:
- PHP syntax check for plugin PHP files when PHP CLI is available.
- JavaScript syntax checks for admin.js and frontend.js when Node is available.
- Assignment SQL now calculates invoice count from the invoices table, not product rows.
- Bulk assignment applies min/max invoice-count constraints server-side.
- Manual checked-profile assignments are revalidated server-side against amount, cohort, and invoice-count filters.
- Direct mobile lookup normalizes Iranian mobile formats before exact lookup in customer phone records.
- Direct assignment posts a concrete customer ID and one selected agent through the existing assignment handler.
