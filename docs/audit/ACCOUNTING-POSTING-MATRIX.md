# Accounting Posting Matrix

The matrix below records the implementation shape and the executed staging evidence. Production was not touched.

| Source module | Trigger | Debit | Credit | Dimensions | Journal status | Reversal |
|---|---|---|---|---|---|---|
| Expense | post/recognize | expense or advance account | payable/cash-advance liability | FY, project, donor/grant where available | staging tests passed | duplicate/idempotency covered by backend tests |
| Expense payment | pay | payable/liability | bank account | FY, bank, project/donor where available | staging tests passed | browser confirmation not tested |
| Reimbursement | approve/pay | expense/payable | bank | FY, employee, project/donor | staging workflow tests passed | browser confirmation not tested |
| Cash advance settlement | settle | expense | employee advance | FY, employee, project | staging workflow tests passed | browser confirmation not tested |
| Procurement/AP | invoice/post/pay | inventory/expense/AP | AP/bank | FY, vendor, project/grant, tax | staging matching and posting tests passed | external bank confirmation not tested |
| Timesheet | post labor cost | labor/project expense | payroll/accrual/clearing | FY, employee, project/activity | staging labor-cost tests passed | payroll integration not tested |
| Fixed asset | capitalize/depreciate/impair/dispose | asset/depreciation/loss | clearing/accumulated depreciation/bank | FY, asset, project where applicable | staging fixed-asset tests passed | browser reversal acceptance not tested |
| Tax | calculate/report/post | tax receivable/expense | tax payable | FY, tax code, source transaction | staging tax calculation/posting tests passed | external e-Bupot/e-Faktur not tested |
| Banking | bank transaction/reconciliation | bank/clearing | source/clearing | FY, bank account | staging banking/reconciliation tests passed | browser reconciliation not tested |

## Commitment versus actual

Purchase request, RFQ, CBA, and PO are commitment/procurement pipeline events until the configured accounting trigger. Supplier invoice/payment, approved expense posting/payment, and labor-cost posting are actual accounting candidates. The staging suite executed the relevant posting paths and the donor-to-report flow reconciled debit 300 = credit 300.

## Controls that must pass before go-live

- journal header and lines balance debit = credit;
- `source_type` + `source_id` prevents duplicate posting;
- fiscal year and dimensions survive every transition;
- reversal creates an auditable reversal, not a destructive edit;
- GL, trial balance, and reports reconcile to posted journals only.

