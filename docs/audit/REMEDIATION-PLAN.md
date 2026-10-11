# Remediation Plan

## Critical — before production

1. ~~Run the completed isolated suite against a disposable staging MySQL database~~ **DONE with isolated MariaDB 10.4**; production was not touched.
2. ~~Verify all posting paths for debit/credit balance, idempotency, duplicate payment prevention, reversal, and fiscal-year propagation in staging~~ **DONE for executed backend scenarios**.
3. ~~Reconcile General Ledger, trial balance, dashboard, and every financial report from posted journals only in staging~~ **DONE for the executed donor-to-report and report test paths**; browser visual reconciliation remains pending.

## High

1. ~~Remove hardcoded PWA project/activity fallback options~~ **DONE**; the form now requires authoritative API data and shows an empty state.
2. Run a Web↔PWA login and transaction synchronization test against an isolated staging URL; standardize token naming or document the intentional compatibility layer. **OPEN / NOT TESTED**.
3. ~~Build a generated dropdown contract test for every relation in `MASTER-DATA-DROPDOWN-MATRIX.md`~~ **DONE**; 38 resources, 114 assertions.
4. ~~Verify tax-code, bank-account, customer, vendor, project, grant, and fiscal-year option API contracts~~ **DONE in staging**; browser rendering remains open.
5. Add/verify year-specific fixtures for any module that intentionally requires records in every fiscal year. **PARTIAL**; global master records are not all duplicated by design.

## Medium

1. Resolve frontend/PWA lint warnings, especially effect-driven state updates and unused callback parameters.
2. Add action-menu tests for every queue/table page and ensure each action has a working detail/edit route or an explicit disabled reason.
3. Add report drill-down tests from summary → journal → source transaction.

## Final blocker

The system remains **NOT READY** for final go-live approval until Web↔PWA browser E2E is executed against isolated staging and production-equivalent MySQL/collation is confirmed. External e-Bupot/e-Faktur validation is also pending.

## Low

1. Reduce frontend bundle size and split large module chunks.
2. Add operator documentation for fiscal-year activation, close, reopening, and historical reporting.

