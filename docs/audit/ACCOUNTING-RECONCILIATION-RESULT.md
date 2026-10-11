# Accounting Reconciliation Result

| Check | Result | Evidence |
|---|---|---|
| Debit equals credit | PASS | Donor-to-report flow reconciled debit 300 and credit 300 |
| Source traceability | PASS | Journal source reference asserted by integration test |
| Duplicate posting/payment controls | PASS | Backend idempotency/payment tests passed in 81-test staging run and final 82-test/672-assertion regression |
| Fiscal-year dimensions | PASS | FY propagation tests and seeded 2025/2026/2027 records passed |
| GL/report path | PASS | Integration and report tests passed |
| Browser drill-down | NOT TESTED | No isolated browser session |
| Production-equivalent MySQL | PARTIAL | Portable default fixed; exact production engine/version still requires confirmation |
