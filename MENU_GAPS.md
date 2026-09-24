# MENU GAP ANALYSIS â€” backend seeder menu vs frontend route

Menus in seeder: 120
Frontend literal routes: 57

| # | Title | Slug | Menu Path | Frontend Route Match | Result |
|---|---|---|---|---|---|
| 1 | Dashboard | dashboard | /dashboard | True | COMPLETE |
| 2 | Master Data | master-data | /master-data | False | NEEDS_ROUTE |
| 3 | Organization & Structure | master-organization-structure | /master-data/organizations | False | NEEDS_ROUTE |
| 4 | Organizations | master-organizations | /master-data/organizations | False | NEEDS_ROUTE |
| 5 | Office Locations | master-office-locations | /master-data/office-locations | False | NEEDS_ROUTE |
| 6 | Departments | master-departments | /master-data/departments | False | NEEDS_ROUTE |
| 7 | Positions | master-positions | /master-data/positions | False | NEEDS_ROUTE |
| 8 | Staff / Employees | master-employees | /master-data/employees | False | NEEDS_ROUTE |
| 9 | Finance & Accounting | master-finance-accounting | /master-data/chart-of-accounts | False | NEEDS_ROUTE |
| 10 | Chart of Accounts | master-chart-of-accounts | /master-data/chart-of-accounts | False | NEEDS_ROUTE |
| 11 | Account Categories | master-account-categories | /master-data/account-categories | False | NEEDS_ROUTE |
| 12 | Bank Accounts | master-bank-accounts | /master-data/bank-accounts | False | NEEDS_ROUTE |
| 13 | Payment Methods | master-payment-methods | /master-data/payment-methods | False | NEEDS_ROUTE |
| 14 | Fx Rates | master-fx-rates | /master-data/exchange-rates | False | NEEDS_ROUTE |
| 15 | Currencies | master-currencies | /master-data/currencies | False | NEEDS_ROUTE |
| 16 | Fiscal Years | master-fiscal-years | /master-data/fiscal-years | False | NEEDS_ROUTE |
| 17 | Accounting Periods | master-accounting-periods | /master-data/accounting-periods | False | NEEDS_ROUTE |
| 18 | Tax Master | master-taxes | /master-data/taxes | False | NEEDS_ROUTE |
| 19 | Funding & Projects | master-funding-projects | /master-data/funding-sources | False | NEEDS_ROUTE |
| 20 | Donors | master-donors | /master-data/donors | False | NEEDS_ROUTE |
| 21 | Grant/Agreements | master-grant-agreements | /master-data/grant-agreements | False | NEEDS_ROUTE |
| 22 | Programs | master-programs | /master-data/programs | False | NEEDS_ROUTE |
| 23 | Projects | master-projects | /master-data/projects | False | NEEDS_ROUTE |
| 24 | Activities | master-activities | /master-data/activities | False | NEEDS_ROUTE |
| 25 | Budget Codes | master-budget-codes | /master-data/budget-lines | False | NEEDS_ROUTE |
| 26 | Sources of Fund (SoF) | master-sof | /master-data/funding-sources | False | NEEDS_ROUTE |
| 27 | Budget & Reporting | master-budget-reporting | /master-data/budget-categories | False | NEEDS_ROUTE |
| 28 | Budget Categories | master-budget-categories | /master-data/budget-categories | False | NEEDS_ROUTE |
| 29 | Budget Templates / Lines | master-budget-templates | /master-data/budget-lines | False | NEEDS_ROUTE |
| 30 | Reporting Categories | master-reporting-categories | /master-data/reporting-categories | False | NEEDS_ROUTE |
| 31 | Reporting Dimensions | master-reporting-dimensions | /master-data/reporting-dimensions | False | NEEDS_ROUTE |
| 32 | Expenses & Assets | master-expenses-assets | /master-data/expense-categories | False | NEEDS_ROUTE |
| 33 | Expense Categories | master-expense-categories | /master-data/expense-categories | False | NEEDS_ROUTE |
| 34 | Document / Expense Types | master-expense-types | /master-data/document-types | False | NEEDS_ROUTE |
| 35 | Asset Categories | master-asset-categories | /master-data/asset-categories | False | NEEDS_ROUTE |
| 36 | Procurement | master-procurement | /master-data/vendors | False | NEEDS_ROUTE |
| 37 | Vendors/Suppliers/Consultants | master-vendors | /master-data/vendors | False | NEEDS_ROUTE |
| 38 | Vendor Categories | master-vendor-categories | /master-data/vendor-categories | False | NEEDS_ROUTE |
| 39 | Items/Services | master-items-services | /master-data/procurement-items | False | NEEDS_ROUTE |
| 40 | Unit of Measures | master-unit-of-measures | /master-data/unit-of-measures | False | NEEDS_ROUTE |
| 41 | Procurement Categories | master-procurement-categories | /master-data/procurement-categories | False | NEEDS_ROUTE |
| 42 | Donor & Grant | donor-grant | /donor-grant | True | COMPLETE |
| 43 | Dashboard | donor-grant-dashboard | /donor-grant/dashboard | False | NEEDS_ROUTE |
| 44 | Donors | donor-grant-donors | /donor-grant/donors | False | NEEDS_ROUTE |
| 45 | Programs / Projects | donor-grant-programs-projects | /donor-grant/programs-projects | False | NEEDS_ROUTE |
| 46 | Grants | donor-grant-grants | /donor-grant/grants | False | NEEDS_ROUTE |
| 47 | Budget | donor-grant-budget | /donor-grant/budget | False | NEEDS_ROUTE |
| 48 | Budget Monitoring | donor-grant-budget-monitoring | /donor-grant/budget-monitoring | False | NEEDS_ROUTE |
| 49 | Grant Reporting | donor-grant-reporting | /donor-grant/reporting | False | NEEDS_ROUTE |
| 50 | Expenses & Approvals | expenses-approvals | /expenses-approvals | False | NEEDS_ROUTE |
| 51 | Dashboard | expenses-dashboard | /expenses-approvals/dashboard | False | NEEDS_ROUTE |
| 52 | Expense Requests | expense-requests | /expenses-approvals/requests | False | NEEDS_ROUTE |
| 53 | Reimbursements | reimbursements | /expenses-approvals/reimbursements | False | NEEDS_ROUTE |
| 54 | Cash Advances | cash-advances | /expenses-approvals/cash-advances | False | NEEDS_ROUTE |
| 55 | Settlement | settlement | /expenses-approvals/settlement | False | NEEDS_ROUTE |
| 56 | Approval Center | approval-center | /expenses-approvals/approvals | True | COMPLETE |
| 57 | Finance Verification | finance-verification | /expenses-approvals/finance-verification | False | NEEDS_ROUTE |
| 58 | Payment Processing | payment-processing | /expenses-approvals/payment-processing | False | NEEDS_ROUTE |
| 59 | Expense Monitoring | expense-monitoring | /expenses-approvals/monitoring | False | NEEDS_ROUTE |
| 60 | Accounting | accounting | /accounting | False | NEEDS_ROUTE |
| 61 | Dashboard | accounting-dashboard | /accounting/dashboard | False | NEEDS_ROUTE |
| 62 | Chart of Accounts | accounting-chart-of-accounts | /accounting/chart-of-accounts | False | NEEDS_ROUTE |
| 63 | Journal | journal | /accounting/journal | True | COMPLETE |
| 64 | General Ledger | general-ledger | /accounting/general-ledger | True | COMPLETE |
| 65 | Accounts Payable | accounts-payable | /accounting/accounts-payable | True | COMPLETE |
| 66 | Accounts Receivable | accounts-receivable | /accounting/accounts-receivable | True | COMPLETE |
| 67 | Banking | banking | /accounting/banking | False | NEEDS_ROUTE |
| 68 | Bank Reconciliation | bank-reconciliation | /accounting/bank-reconciliation | True | COMPLETE |
| 69 | Fixed Assets | fixed-assets | /accounting/fixed-assets | True | COMPLETE |
| 70 | Period Closing | period-closing | /accounting/period-closing | False | NEEDS_ROUTE |
| 71 | Procurement | procurement | /procurement | False | NEEDS_ROUTE |
| 72 | Dashboard | procurement-dashboard | /procurement/dashboard | False | NEEDS_ROUTE |
| 73 | Purchase Request | procurement-purchase-request | /procurement/purchase-requests | True | COMPLETE |
| 74 | RFQ & CBA | procurement-rfq-cba | /procurement/rfq-cba | True | COMPLETE |
| 75 | Vendors | procurement-vendors | /procurement/vendors | False | NEEDS_ROUTE |
| 76 | Purchase Orders | procurement-purchase-orders | /procurement/purchase-orders | True | COMPLETE |
| 77 | Contracts | procurement-contracts | /procurement/contracts | False | NEEDS_ROUTE |
| 78 | Supplier Contract Notification | procurement-scn | /procurement/scn | True | COMPLETE |
| 79 | Goods Receipts | procurement-goods-receipts | /procurement/goods-receipts | True | COMPLETE |
| 80 | Supplier Invoices | procurement-supplier-invoices | /procurement/supplier-invoices | True | COMPLETE |
| 81 | Waiver & Justification | procurement-waivers | /procurement/waivers | False | NEEDS_ROUTE |
| 82 | Taxes | taxes | /taxes | False | NEEDS_ROUTE |
| 83 | Dashboard | taxes-dashboard | /taxes/dashboard | False | NEEDS_ROUTE |
| 84 | Tax Transactions | taxes-transactions | /taxes/transactions | False | NEEDS_ROUTE |
| 85 | PPh | taxes-pph | /taxes/pph | False | NEEDS_ROUTE |
| 86 | VAT / PPN | taxes-vat | /taxes/vat | False | NEEDS_ROUTE |
| 87 | e-Bupot | taxes-ebupot | /taxes/e-bupot | False | NEEDS_ROUTE |
| 88 | e-Faktur | taxes-efaktur | /taxes/e-faktur | False | NEEDS_ROUTE |
| 89 | Tax Calendar | taxes-calendar | /taxes/calendar | False | NEEDS_ROUTE |
| 90 | Tax Reports | taxes-reports | /taxes/reports | False | NEEDS_ROUTE |
| 91 | Tax Calculator | tax-calculator | /tax-calculator | True | COMPLETE |
| 92 | Timesheet | timesheet | /timesheet | True | COMPLETE |
| 93 | Dashboard | timesheet-dashboard | /timesheet/dashboard | False | NEEDS_ROUTE |
| 94 | My Timesheet | timesheet-my | /timesheet/my-timesheet | False | NEEDS_ROUTE |
| 95 | Team Timesheet | timesheet-team | /timesheet/team-timesheet | False | NEEDS_ROUTE |
| 96 | Project Timesheet | timesheet-project | /timesheet/project-timesheet | False | NEEDS_ROUTE |
| 97 | Approval | timesheet-approval | /timesheet/approval | False | NEEDS_ROUTE |
| 98 | Reports | timesheet-reports | /timesheet/reports | False | NEEDS_ROUTE |
| 99 | Reports | reports | /reports | True | COMPLETE |
| 100 | Financial Reports | reports-financial | /reports/financial | False | NEEDS_ROUTE |
| 101 | Budget Reports | reports-budget | /reports/budget | False | NEEDS_ROUTE |
| 102 | Donor / Grant Reports | reports-donor-grant | /reports/donor-grant | False | NEEDS_ROUTE |
| 103 | Project Reports | reports-project | /reports/project | False | NEEDS_ROUTE |
| 104 | Procurement Reports | reports-procurement | /reports/procurement | False | NEEDS_ROUTE |
| 105 | Tax Reports | reports-tax | /reports/tax | False | NEEDS_ROUTE |
| 106 | Management Reports | reports-management | /reports/management | False | NEEDS_ROUTE |
| 107 | Administration | administration | /administration | False | NEEDS_ROUTE |
| 108 | Staff | admin-staff | /administration/staff | True | COMPLETE |
| 109 | Organization | admin-organization | /administration/organization | True | COMPLETE |
| 110 | Department | admin-department | /administration/department | True | COMPLETE |
| 111 | Documents | admin-documents | /administration/documents | True | COMPLETE |
| 112 | Contracts | admin-contracts | /administration/contracts | True | COMPLETE |
| 113 | Audit Log | admin-audit-logs | /administration/audit-logs | True | COMPLETE |
| 114 | Settings | settings | /settings | True | COMPLETE |
| 115 | Users | settings-users | /settings/users | False | NEEDS_ROUTE |
| 116 | Roles & Permissions | settings-roles-permissions | /settings/roles-permissions | True | COMPLETE |
| 117 | Approval Workflow | settings-approval-workflow | /settings/approval-workflow | False | NEEDS_ROUTE |
| 118 | System Settings | settings-system | /settings/system | True | COMPLETE |
| 119 | Integrations | settings-integrations | /settings/integrations | False | NEEDS_ROUTE |
| 120 | Notifications | settings-notifications | /settings/notifications | False | NEEDS_ROUTE |
