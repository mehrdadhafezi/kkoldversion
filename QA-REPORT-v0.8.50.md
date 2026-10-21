# QA Report — v0.8.50 Controlled Job 500

- Golden snapshot: `2026-08-29 09:09:06`
- Golden SHA256: `3c264a9aec67289cb9b9418d8d1121445e795d3a8505306ce5f23b69788e289a`
- Manifest SHA256: `df63255499be31a49968b7315f0e9a6db4f4ba3f220c5042bcf4b38ec87e103f`
- Profiles: 500 unique source / 500 unique target
- Exact records: 1,500 (1,000 activities + 500 returned assignments)
- Chunk size: 50 profiles; total chunks: 10
- Candidate class: same proven returned-assignment low-complexity class as v0.8.49
- No Invoice / Phone / CustomerID / Merge / Payment writes
- Each chunk: live preflight, transaction, row locks, Golden hash revalidation, target evidence lock, merge/activity guard, exact-row hash, journal, post-write verification
- Run progress is written in the same DB transaction as each chunk, preventing replay after HTTP 504
- Previous committed chunks are not auto-rolled-back if a later chunk blocks
- Whole-job rollback is intentionally disabled pending journal/hash review
- PHP lint: PASS
- admin.js syntax: PASS
