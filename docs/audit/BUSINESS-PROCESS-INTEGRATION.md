# Business Process Integration Audit

## Donor, grant, program, project

The backend exposes donor/grant integrated routes, project/framework/workplan routes, budget routes, and reporting routes. Models and requests carry foreign keys for donor, grant, program, project, and fiscal year in the inspected paths.

**Status: PASS for executed backend scenarios.** Donor/grant/project/framework/workplan/budget integration tests passed in isolated staging. Multi-grant allocation and restriction-specific acceptance remain outside the executed scenario.

## Expense and approval

Observed route chain:

`POST /api/v1/expenses/requests → submit → verify → approve/reject → post → pay → settle/return/reimbursement pay`.

`ExpenseRequestController` propagates fiscal year and dimensions into accrual, payment, settlement, return, reimbursement, bank, and tax records. `ApprovalCenterController` maps expense, settlement, payment, tax, journal, AP, AR, timesheet, asset, and bank reconciliation work items.

**Status: PASS for executed backend scenarios.** Workflow, posting, idempotency, and payment tests passed in staging. Live Web↔PWA status comparison remains NOT TESTED.

## Timesheet

Observed chain:

`timesheet entry → submit → approve/reject → post-labor-cost → journal/GL/reporting`.

`TimesheetEntryController` and `TimesheetWorkflowTest` cover the route shape. Fiscal year is present on the timesheet model and labor-cost journal path.

**Status: PASS for executed backend scenarios.** Hours/rate/duplicate/project authorization tests passed in staging; PWA build/test also passed. Browser/device synchronization remains NOT TESTED.

## Procurement

Observed chain:

`purchase request → RFQ → quotation/CBA → PO → contract/SCN → goods receipt → supplier invoice → three-way match → AP/payment`.

`routes/api.php` contains the fulfillment and three-way-match routes, and supplier invoice models include fiscal year and posting fields.

**Status: PASS for executed backend scenarios.** Procurement matching, fiscal-year propagation, and posting tests passed in staging. External vendor/bank confirmation remains NOT TESTED.

## Accounting and reporting

Observed sources include expense, AP, AR, timesheet labor cost, fixed assets, tax, banking, journals, GL, and report endpoints. A new integration test covers donor/grant/project/budget/expense/payment/GL/report assertions.

**Status: PASS for isolated staging runtime.** The 81-test suite passed with 558 assertions, including the donor-to-report reconciliation. Browser drill-down and production-equivalent database acceptance remain NOT TESTED.

