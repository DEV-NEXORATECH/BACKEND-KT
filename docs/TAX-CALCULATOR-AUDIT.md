# Tax Calculator PPh 21 — Audit and Remediation

## Scope

Audit focused on the existing Tax Calculator flow and its Laravel `v1/tax/calculate` API. No new tax module or journal side effect was introduced.

## Findings and fixes

| Finding | Root cause | Fix | Status |
|---|---|---|---|
| PPh 21 non-staff selected one manual tariff layer | Frontend used `consultantBracket` and calculated locally | Removed manual layer selector; backend resolves DPP and progressive layers from Tax Master configuration | Fixed in code |
| DPP and progressive details were not returned | Generic tax service returned only one rate and tax amount | Response now includes gross, DPP percentage, DPP, effective rate, progressive layers, tax amount, take-home pay, effective date, and tax year | Fixed in code |
| Tax rules were not structured in Tax Master | `taxes` had only `rate_percent` and free-text rule | Added nullable `calculation_config` JSON column and seeded PPh 21 non-staff configuration | Migration + seeder required |
| Prior-period accumulation was unavailable | Calculator accepted only current gross | Added `accumulated_gross`; current withholding is calculated as cumulative tax minus prior cumulative tax | Fixed in code |
| Daily worker calculation was frontend-only | Daily result used a local hardcoded helper | Daily requests now use the same backend endpoint and Tax Master daily brackets | Fixed in code |
| Historical effective-date behavior | Existing service already rejected inactive date ranges | Preserved and covered by existing effective-date feature test | Existing behavior retained |

## API contract

`POST /api/v1/tax/calculate` accepts `tax_id`, `gross_amount`, `tax_date`, `recipient_category`, `accumulated_gross`, `has_npwp`, and `work_days` for PPh 21 scenarios. The backend remains authoritative; the frontend only formats the response.

## Verification evidence

- PHP syntax checks: passed for service, controller, model, migration, seeder, and feature test.
- Direct service calculation: passed for gross Rp15,000,000, DPP 50%, prior accumulated gross Rp120,000,000; result tax Rp1,125,000 with two progressive layers.
- Frontend lint: passed with existing repository warnings; no lint error.
- Frontend production build: passed.
- PHPUnit `tests/Feature/TaxTransactionTest.php`: not completed because the test process stalled during database bootstrap in the current environment. No PASS is claimed for runtime database tests.

## Remaining risk

Runtime MySQL/PHPUnit execution and browser verification still need to run in an isolated staging database. The seeded `calculation_config` migration must be applied before using the new Tax Master configuration.
