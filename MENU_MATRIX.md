# MENU x ROUTE x API x PERMISSION â€” Cross-Reference Matrix

Dihasilkan otomatis dari source aktual kedua repo (bukan tebakan).
Source: backend C:\Users\raulm\Downloads\Bakcend-KT\database\seeders\DatabaseSeeder.php
Source: frontend C:\Users\raulm\Downloads\Frontend-KT\src\App.jsx

| # | Menu slug | Base Path (seeder) | Status | Route(s) frontend | Permission backend |
|---|---|---|---|---|---|

## Route paths yang terdaftar di App.jsx (63)

```
login
dashboard
funding-projects/donor-grant
funding-projects/donors
donor-grant
donors
funding-projects/program-project
funding-projects/programs
funding-projects/budget
funding-projects/budget-monitoring
funding-projects/budget-reallocation
program-project
programs
expenses-approvals/approvals
expenses-approvals/approval
expenses-approvals/expenses
expenses-approvals/cash-advance
expenses-approvals/reimbursement
expenses-approvals/timesheet
expenses
timesheet
approvals
approval
accounting/journal
accounting/general-ledger
accounting/recurring-journals
accounting/bank-reconciliation
accounting/accounts-payable
accounting/ap
accounting/accounts-receivable
accounting/ar
accounting/fixed-assets
fixed-assets
procurement/purchase-requests
procurement/purchase-orders
procurement/goods-receipts
procurement/supplier-invoices
procurement/pr
procurement/rfq-cba
procurement/scn
reports
reports/forecast
reports/custom
master-data/*
tax-calculator
accounting/tax-calculator
accounting/tax
tax
administration/master-menu
administration/audit-logs
administration/staff
administration/organization
administration/department
administration/documents
administration/contracts
administration/role-access
settings/roles-permissions
administration/approval-matrix/*
administration/approval/*
administration/approval-matrices/*
settings/approval-workflow/*
settings
settings/system
```

## Permission slugs yang dipakai di routes/api.php (74)

```
accounting.journal.create
accounting.journal.delete
accounting.journal.post
accounting.journal.reverse
accounting.journal.review
accounting.journal.submit
accounting.journal.update
accounting.journal.view
accounting.view
ap.pay
ap.post
ap.view
ar.create
ar.post
ar.receive
ar.view
asset.capitalize
asset.create
asset.depreciate
asset.dispose
asset.transfer
asset.view
audit.view
banking.view
budget.approve
budget.update
budget.validate
budget.view
dashboard.view
expense.approve
expense.create
expense.pay
expense.post
expense.submit
expense.verify
expense.view
master-data
master-data.export
master-data.manage
master-data.view
master-menu.manage
master-menu.view
payments.view
procurement.cba.approve
procurement.cba.create
procurement.grn.create
procurement.grn.view
procurement.invoice.create
procurement.invoice.match
procurement.invoice.view
procurement.po.approve
procurement.po.create
procurement.po.view
procurement.pr.approve
procurement.pr.create
procurement.pr.delete
procurement.pr.submit
procurement.pr.update
procurement.pr.view
procurement.rfq.create
procurement.rfq.view
reports.export
reports.view
role-access.manage
role-access.view
settings.manage
tax.manage
tax.view
timesheet.approve
timesheet.create
timesheet.submit
timesheet.update
timesheet.view
user.manage
```

## Menu yang ROUTE_MISMATCH (path menu tidak ada di frontend)

```
```
