# Final Remediation Report

Date: 2026-10-11

## Completed

| Finding | Root cause | Fix | Evidence | Status |
|---|---|---|---|---|
| PWA invented project/activity options | Form supplied hardcoded fallback records | Options now come only from Master Data API; empty/error states block invalid submit | PWA build and test pass | PASS |
| Fiscal-year propagation gaps | Several controllers did not carry FY into downstream records | Added propagation and validation across expense, AP/AR, tax, GL, reports, fixed assets, and timesheet posting | 81 backend tests, 558 assertions | PASS |
| Seeder sequencing | Fiscal-year cloning ran before feature fixtures | ProcessDataSeeder now runs feature seeders before fiscal-year demo data | Clean staging seed pass | PASS |
| Dropdown API contract coverage | Option endpoints lacked one consolidated regression check | Added `DropdownContractTest` for 38 resources | 1 test, 114 assertions | PASS |
| Cross-engine collation | MySQL default used a collation unsupported by MariaDB staging | Portable MySQL default changed to `utf8mb4_unicode_ci`; explicit `DB_COLLATION` override remains supported | PHP config inspection; staging run succeeded with override | PASS/PARTIAL |

## Regression evidence

The final backend regression passed **82 tests and 672 assertions** with `SESSION_DRIVER=array`. A prior `--no-configuration` run failed only because that flag bypassed `phpunit.xml` and defaulted to database sessions without the `sessions` table; the official test environment passed after the explicit session setting.

## Remaining

Web↔PWA browser E2E and external e-Bupot/e-Faktur remain NOT TESTED because no isolated staging browser session and credentials were available.
