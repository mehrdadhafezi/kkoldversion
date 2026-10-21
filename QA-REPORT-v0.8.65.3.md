# QA Report v0.8.65.3

- Prevent implicit corrected-declared fallback to invoice total.
- Persist deliberate corrected declared amount to the latest interaction (canonical source) before recalculation.
- Block direct corrected completion when staged/persisted receipts exist.
- Validate staged receipt totals before WordPress attachment materialization; mismatch keeps staged files intact.
- Preserve already-materialized receipts in race-condition mismatch fallback instead of deleting evidence.
- Deficiency timeline now shows all deficiency cases for the same customer, including prior assignment/contact history.
- Based on production diagnosis for C064286 / customer 64470: case 5141 completed with an implicit corrected amount, case 5369 was recreated, and 12 staged receipts remained with zero attachment rows.
