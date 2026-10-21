# Biawin CRM v0.6.2

## Import column mapping redesign

- File headers are now read automatically after selecting a CSV/XLSX file.
- Each real source header is shown as a row with sample values from the file.
- For each source header, the operator selects the matching CRM field from a dropdown or chooses «نادیده بگیر».
- Common Persian/English headers are auto-detected.
- The last successful mapping for the current WordPress user is reused when the same headers appear again.
- Duplicate target mappings are blocked both in JavaScript and server-side.
- Invoice code mapping is mandatory.
- Unknown/extra columns can be ignored safely.
- Duplicate or blank source headers are made unique so they can be mapped independently.
- Added an AJAX header-inspection endpoint; selecting a file does not require a page refresh to discover columns.
- Mapping is revalidated against the actual uploaded file during Dry Run; client-side values are never trusted alone.
- Added a fallback when mbstring is unavailable for header normalization.
