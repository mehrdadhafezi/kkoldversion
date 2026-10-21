# QA Report v0.6.3

Static checks completed:
- PHP syntax check: all plugin PHP files pass.
- JavaScript syntax check: admin.js and frontend.js pass.
- Version bump: 0.6.3.
- Database migration includes invoices.extra_json.
- Journey panel and profile root contain explicit customer id attributes.
- AJAX journey code uses currentCustomerId() fallback chain.
- Header inspection uses inspect_file() and no longer calls parse_file() for the inspect AJAX endpoint.

Runtime WordPress/MySQL testing should still be performed in staging/local before production deployment.
