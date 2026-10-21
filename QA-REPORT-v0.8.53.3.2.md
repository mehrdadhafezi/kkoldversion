# QA Report — v0.8.53.3.2

- Sequential Auto Runner added without changing Frozen Plan, eligible scope, mapping, CHUNK_SIZE=50, or commit semantics.
- AJAX endpoint requires manage_options, WordPress nonce, exact confirmation phrase, exact active Run UUID, and exact Frozen Plan hash.
- Each request invokes exactly one existing commit_next_chunk().
- Runner never issues parallel requests.
- Hard-stop conditions: HTTP/AJAX/JSON error, chunk ok=false, Run UUID/hash mismatch, Processed non-advance/jump, unexpected quarantine reason, or unexpected run-state change.
- Expected fail-closed quarantine reasons allowed: ACTIVE_ASSIGNMENT_CONFLICT, TARGET_ROW_MISSING.
- Manual stop waits for current chunk to finish.
