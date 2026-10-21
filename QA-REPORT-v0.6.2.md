# QA Report v0.6.2

Checks performed:

- PHP syntax check on every PHP source file.
- JavaScript syntax check for admin.js and frontend.js.
- Tested CSV parsing with Persian headers.
- Tested duplicate source headers; duplicate mobile columns remain independently addressable.
- Tested header auto-detection for: customer name, invoice code, phone, amount paid, products, and second phone.
- Mapping validation exists on both client and server.
- Invoice-code mapping is mandatory.
- Unsupported mapping targets are rejected server-side.
