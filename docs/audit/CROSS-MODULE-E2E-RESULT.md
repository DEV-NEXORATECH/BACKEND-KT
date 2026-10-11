# Cross-Module E2E Result

| Flow | Evidence | Status |
|---|---|---|
| Donor → Grant → Project → Budget → Expense → Payment → GL → Report | `EndToEndDonorToReportTest`; included in staging PHPUnit; debit 300 = credit 300 | PASS |
| Expense approval and payment idempotency | Backend feature tests in 81-test staging run | PASS |
| Procurement → AP → Payment → GL | Procurement/AP feature tests in staging run | PASS |
| Timesheet → Labor Cost → Journal | `TimesheetWorkflowTest` in staging run | PASS |
| Tax transaction → posting → report | Tax feature tests in staging run | PASS |
| Web → PWA → Web live transaction | No isolated browser session/credentials | NOT TESTED |
| PWA → Web → PWA live transaction | No isolated browser session/credentials | NOT TESTED |
