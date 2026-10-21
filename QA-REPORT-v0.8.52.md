# QA Report — v0.8.52

- Frozen planner source: controlled-job-v0.8.51.1-planner-20260830-211826.json
- Frozen profiles: 988
- Exact planned records: 2964 (1976 activities + 988 assignments)
- Chunk size: 50; total chunks: 20; final chunk: 38 profiles
- Confirmation phrase: COMMIT-JOB-988
- Mode: controlled_job_988_v0852
- Frozen plan timestamp guard: 2026-08-30 21:17:25
- Plan file SHA256: 26aafe8528d957a665ee51c842972a5669d5875e034098c3a6d5918e2e104fdc
- Profiles SHA256 (planner): 15bae1eafeab94fa31709092bb651fe84a15a18a390e1c5d736cbd2338bb039d
- PHP lint: PASS (all PHP files)
- JS syntax: PASS (all JS files)

Safety behavior retained from validated v0.8.50.3/0.8.50.4 engine:
- per-chunk transaction
- row locks and under-lock revalidation
- exact-record projected hash guards
- source/target customer hash guards
- live target phone/invoice evidence
- active assignment conflict quarantine
- manual merge guard
- post-plan operational activity guard
- per-profile quarantine without writes to quarantined profile
- journal before/after hashes
- post-chunk verification
- resumable progress and stale-progress reconciliation
- no whole-job rollback
- no invoice/phone/CustomerID/merge/payment/declared_paid writes

v0.8.52 start semantics:
- A complete 988-profile preflight is required to create the run.
- Profiles that became unsafe after planning are allowed to be reported BLOCKED and are quarantined when their chunk is processed; they do not prevent safe profiles in other chunks from proceeding.
