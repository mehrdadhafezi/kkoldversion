# BIAWIN CRM
# Package Hygiene - v0.8.57.1

## Production Data Policy

The Production plugin package must contain only runtime data required for read-only reporting semantics.

## Data Files Kept

| File | Classification | Reason |
|---|---|---|
| `data/blacklisted-customer-ids.txt` | `RUNTIME_REQUIRED` | Used by the population SOT and Guard/blacklist-aware reporting paths to identify official blacklisted live customers. |

## Data Files Excluded

| File | Classification | Reason |
|---|---|---|
| `data/controlled-batch-v0.8.48.json` | `HISTORICAL_EVIDENCE` | Historical controlled reconstruction input, not required by dashboard runtime. |
| `data/controlled-batch-v0.8.49.json` | `HISTORICAL_EVIDENCE` | Historical controlled reconstruction input, not required by dashboard runtime. |
| `data/controlled-job-v0.8.50.json` | `HISTORICAL_EVIDENCE` | Historical controlled reconstruction input, not required by dashboard runtime. |
| `data/controlled-job-v0.8.52.json` | `MANUAL_RECONSTRUCTION_TOOLING` | Referenced by controlled reconstruction tooling, not by normal dashboard/reporting runtime. |
| `data/frozen-canonical-plan-v0.8.53.2.json` | `MANUAL_RECONSTRUCTION_TOOLING` | Referenced by manual/final blacklist and post-Run #7 tooling, not by the main management dashboard SOT. |
| `data/pilot-reconstruction-v0.8.47.json` | `HISTORICAL_EVIDENCE` | Historical pilot evidence, not required by dashboard runtime. |

## Runtime Reference Result

Runtime reporting and dashboard classes require the blacklist TXT file. The JSON artifacts are maintenance/reconstruction evidence and are intentionally not shipped in the Production plugin package. External archives are not modified by this packaging policy.

## Final Package Scan Requirements

The final ZIP must be scanned after extraction and must not contain:

- `.git`
- `.ndjson`
- SQL dumps
- backup archives
- customer/reconstruction JSON artifacts
- credentials or secrets
- unexpected large artifacts outside the runtime blacklist source
