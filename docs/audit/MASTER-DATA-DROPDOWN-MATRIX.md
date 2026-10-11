# Master Data & Dropdown Integration Matrix

Status combines source inspection with the isolated staging contract test. `NOT TESTED` means the browser interaction or a specific dependent-field scenario was not executable in this environment.

| Dropdown | Web/PWA source | Backend source | Table/model | Status | Finding |
|---|---|---|---|---|---|
| Organization | shared master-data forms | master-data API resources | organizations / Organization | PARTIAL | Endpoint and relation exist; live option/filter test blocked |
| Department | shared forms | master-data API resources | departments / Department | PARTIAL | Scope and permissions need DB test |
| Office Location | shared forms | master-data API resources | office_locations | PARTIAL | Static mapping found; runtime not tested |
| Position | staff/admin forms | master-data API resources | positions | PARTIAL | Runtime option availability not tested |
| Staff/Employee | expense/timesheet forms | staff/employee endpoints | users/staff/employees | PARTIAL | Assignment and permission checks need runtime test |
| Fiscal Year | `FiscalYearSelector`, global API context | `FiscalYearScopedTrait`, fiscal-year controllers | fiscal_years | PASS | Staging contains 2025/2026/2027; option contract and backend propagation passed |
| Accounting Period | accounting forms | accounting-period request/controller | accounting_periods | PARTIAL | Request validates fiscal year; close/open runtime untested |
| Donor | donor/grant pages | donor controllers/resources | donors | PARTIAL | Relationship exists; active/scope behavior needs DB test |
| Funding Source | master-data tabs | master-data API resources | funding_sources | PARTIAL | Menu/page source exists; runtime seed/options untested |
| Grant Agreement | grant and budget forms | grant agreement controllers | grant_agreements | PARTIAL | Used by project/budget paths; FK combinations need runtime test |
| Program | programs/project forms | program controller/resource | programs | PARTIAL | FY field and table column exist; historical seed data unverified |
| Project | donor/grant, workplan, expense, procurement | project controllers | projects | PARTIAL | Parent filtering needs runtime test |
| Framework/Workplan/Activity | framework/workplan forms | logframe/workplan/activity endpoints | project_frameworks, workplans, activities | PARTIAL | Routes exist; dependent reset/authorization not fully tested |
| Budget Category/Line | budget forms | budget controllers/requests | budget_categories, budget_lines | PARTIAL | FY validation exists; available-budget reconciliation not tested |
| Expense Category/Document Type | expense forms | master-data resources | expense_categories, document_types | PARTIAL | Source exists; active-only transaction rule not proven |
| Bank Account | dashboard/banking/payment forms | banking and payment controllers | bank_accounts | PASS/PARTIAL | Staging option contract passed; dashboard browser rendering still untested |
| Vendor | procurement forms | vendor/procurement controllers | vendors | PARTIAL | Used in PR/PO/invoice chain; live options untested |
| Item/Service, UOM | procurement forms | procurement master endpoints | items/services, units_of_measure | PARTIAL | Relationship source exists; dependent filtering untested |
| Chart of Accounts | journal/GL forms | COA controller/request | chart_of_accounts | PARTIAL | Parent-account validation exists; active/header behavior needs DB test |
| Tax Code | tax/procurement/AP forms | tax transaction controller | tax_codes/tax_transactions | PASS/PARTIAL | Staging option contract and backend tax tests pass; deployed browser response untested |
| Currency/Payment Method | finance forms | master-data resources | currencies, payment_methods | PARTIAL | Requests include references; live active-only options untested |
| Reporting Dimension | budget/reporting forms | reporting dimension resources | reporting_dimensions | PARTIAL | Table/source exists; report reconciliation untested |

## PWA-specific finding

`kaoem-telapak-pwa/src/presentation/pages/timesheet/components/TimesheetFormModal.tsx` no longer contains fallback project/activity records. Empty API results now show an informative empty state and project timesheet submission is blocked until valid Master Data is selected.

## Required runtime checks

For each row: verify endpoint response, active-only filtering for new transactions, parent-child reset, organization/project/fiscal-year scope, authorization, and rejection of mismatched foreign keys in backend requests. The automated staging contract currently covers endpoint availability, success shape, option payload for all 38 resources, and one full Donor → Grant → Project → Activity → Budget Line dependency chain; it does not replace browser/device interaction tests.

## Executed staging evidence

`tests/Feature/DropdownContractTest.php` passed with 2 focused tests and 141 assertions against the isolated test database. The suite exercised 38 `/api/v1/master/{resource}?options=1&fiscal_year_id=...` resources plus the dependent donor chain. No hardcoded PWA project/activity fallback remains.

