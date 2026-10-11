# Final Test Result

Audit date: 2026-10-11.

| Command/check | Result | Evidence |
|---|---|---|
| Frontend targeted queue smoke test | PASS | 4 tests passed |
| Frontend full Vitest suite | PASS | 9 files, 63 tests passed |
| Frontend build | PASS | Vite build completed; large-chunk warnings only |
| Frontend lint | PASS WITH WARNINGS | warnings for unused/dependency patterns; no hard failure |
| PWA build | PASS | TypeScript + Vite build completed |
| PWA test | PASS | 1 test passed |
| PWA lint | PASS WITH WARNINGS | effect/state and unused-parameter warnings remain |
| Backend changed-file PHP syntax | PASS | syntax checks completed |
| Backend PHPUnit | PASS | Isolated MariaDB staging: 81 tests, 558 assertions; final SQLite regression: 82 tests, 672 assertions |
| Backend migration | PASS | 140 migrations completed on isolated `backend_kt_staging` at `127.0.0.1:3307` |
| Backend `ProcessDataSeeder` | PASS | Full `DatabaseSeeder` including ProcessDataSeeder and FiscalYearDemoSeeder completed on clean staging database |
| Dropdown contract suite | PASS | 2 focused tests, 141 assertions: 38 endpoint contracts plus Donor → Grant → Project → Activity → Budget Line dependency |
| New donor→report integration test | PASS | included in 81-test run |
| Web/PWA browser E2E | NOT TESTED | Available browser reached deployment login; no isolated staging session/credentials were available; no transaction was submitted |
| External e-Bupot/e-Faktur integration | NOT TESTED | No external integration environment was used |
| Production deployment test | NOT TESTED | intentionally not performed |

## Accounting reconciliation evidence

The cross-module E2E test passed assertions for fiscal-year propagation, bank transaction creation, balanced GL totals (debit 300 / credit 300 for the test flow), source reference trace, and report actuals. Existing reports, AP/AR, cash advance, procurement, tax, fixed asset, journal, period closing, and timesheet tests also passed in the same 81-test run.

## Interpretation

Frontend, PWA, backend migration, seeders, PHPUnit, dropdown contracts, and cross-module integration gates are green in isolated environments. The MySQL-compatible staging run used MariaDB 10.4 with `DB_COLLATION=utf8mb4_unicode_ci`; the application default is now portable `utf8mb4_unicode_ci`. Final SQLite regression passed 82 tests and 672 assertions. Remaining release risks are real browser/device Web↔PWA synchronization, external tax integrations, and confirmation of the production engine/version; these are explicitly not tested here.

