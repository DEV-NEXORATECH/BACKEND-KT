<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $roles = collect([
            ['slug' => 'super-admin', 'name' => 'SUPER ADMIN'],
            ['slug' => 'admin-system-owner', 'name' => 'ADMIN / SYSTEM OWNER'],
            ['slug' => 'finance', 'name' => 'FINANCE'],
            ['slug' => 'finance-manager', 'name' => 'FINANCE MANAGER'],
            ['slug' => 'budget-holder', 'name' => 'BUDGET HOLDER'],
            ['slug' => 'manager', 'name' => 'MANAGER'],
            ['slug' => 'procurement', 'name' => 'PROCUREMENT'],
            ['slug' => 'staff', 'name' => 'STAFF'],
            ['slug' => 'executive-director', 'name' => 'Executive Director'],
            ['slug' => 'board-director', 'name' => 'Board of Trustees'],
            ['slug' => 'project-manager', 'name' => 'Project Manager'],
            ['slug' => 'finance-officer', 'name' => 'Finance Officer'],
        ])->mapWithKeys(fn ($role) => [
            $role['slug'] => Role::updateOrCreate(
                ['slug' => $role['slug']],
                ['name' => $role['name']],
            ),
        ]);

        $permissionsList = [
            ['name' => 'View dashboard', 'slug' => 'dashboard.view'],
            ['name' => 'View funding and projects', 'slug' => 'funding-projects.view'],
            ['name' => 'Manage funding and projects', 'slug' => 'funding-projects.manage'],
            ['name' => 'View expenses and approvals', 'slug' => 'expenses-approvals.view'],
            ['name' => 'Approve expenses', 'slug' => 'expenses.approve'],
            ['name' => 'View accounting', 'slug' => 'accounting.view'],
            ['name' => 'View reports', 'slug' => 'reports.view'],
            ['name' => 'Export reports', 'slug' => 'reports.export'],
            ['name' => 'Manage administration', 'slug' => 'administration.manage'],
            ['name' => 'View master menu', 'slug' => 'master-menu.view'],
            ['name' => 'Manage master menu', 'slug' => 'master-menu.manage'],
            ['name' => 'View role access', 'slug' => 'role-access.view'],
            ['name' => 'Manage role access', 'slug' => 'role-access.manage'],
            ['name' => 'Manage settings', 'slug' => 'settings.manage'],
            ['name' => 'View audit logs', 'slug' => 'audit.view'],
            ['name' => 'Manage users', 'slug' => 'user.manage'],
            ['name' => 'Manage roles', 'slug' => 'role.manage'],
            ['name' => 'Manage permissions', 'slug' => 'permission.manage'],
            ['name' => 'Manage menus', 'slug' => 'menu.manage'],

            // Master Data Granular Permissions
            ['name' => 'View Master Data', 'slug' => 'master-data.view'],
            ['name' => 'Manage Master Data', 'slug' => 'master-data.manage'],
            ['name' => 'Export Master Data', 'slug' => 'master-data.export'],
            ['name' => 'View accounting', 'slug' => 'accounting.coa.view'],
            ['name' => 'Manage accounting', 'slug' => 'accounting.coa.manage'],
            ['name' => 'View journal', 'slug' => 'accounting.journal.view'],
            ['name' => 'Create journal', 'slug' => 'accounting.journal.create'],
            ['name' => 'Update journal', 'slug' => 'accounting.journal.update'],
            ['name' => 'Delete journal', 'slug' => 'accounting.journal.delete'],
            ['name' => 'Submit journal', 'slug' => 'accounting.journal.submit'],
            ['name' => 'Review journal', 'slug' => 'accounting.journal.review'],
            ['name' => 'Post journal', 'slug' => 'accounting.journal.post'],
            ['name' => 'Reverse journal', 'slug' => 'accounting.journal.reverse'],
            ['name' => 'View tax', 'slug' => 'tax.view'],
            ['name' => 'Manage tax', 'slug' => 'tax.manage'],
            ['name' => 'View budget', 'slug' => 'budget.view'],
            ['name' => 'Manage budget', 'slug' => 'budget.manage'],
            ['name' => 'Validate budget', 'slug' => 'budget.validate'],
            ['name' => 'View procurement PR', 'slug' => 'procurement.pr.view'],
            ['name' => 'Create procurement PR', 'slug' => 'procurement.pr.create'],
            ['name' => 'Update procurement PR', 'slug' => 'procurement.pr.update'],
            ['name' => 'Delete procurement PR', 'slug' => 'procurement.pr.delete'],
            ['name' => 'Submit procurement PR', 'slug' => 'procurement.pr.submit'],
            ['name' => 'Approve procurement PR', 'slug' => 'procurement.pr.approve'],
            ['name' => 'View RFQ', 'slug' => 'procurement.rfq.view'],
            ['name' => 'Create RFQ', 'slug' => 'procurement.rfq.create'],
            ['name' => 'View CBA', 'slug' => 'procurement.cba.view'],
            ['name' => 'Create CBA', 'slug' => 'procurement.cba.create'],
            ['name' => 'Approve CBA', 'slug' => 'procurement.cba.approve'],
            ['name' => 'View purchase orders', 'slug' => 'procurement.po.view'],
            ['name' => 'Create purchase orders', 'slug' => 'procurement.po.create'],
            ['name' => 'Approve purchase orders', 'slug' => 'procurement.po.approve'],
            ['name' => 'View goods receipts', 'slug' => 'procurement.grn.view'],
            ['name' => 'Create GRN', 'slug' => 'procurement.grn.create'],
            ['name' => 'View supplier invoices', 'slug' => 'procurement.invoice.view'],
            ['name' => 'Create supplier invoice', 'slug' => 'procurement.invoice.create'],
            ['name' => 'Run invoice match', 'slug' => 'procurement.invoice.match'],
            ['name' => 'View AP', 'slug' => 'ap.view'],
            ['name' => 'Post AP', 'slug' => 'ap.post'],
            ['name' => 'Pay AP', 'slug' => 'ap.pay'],
            ['name' => 'View AR', 'slug' => 'ar.view'],
            ['name' => 'Create AR invoice', 'slug' => 'ar.create'],
            ['name' => 'Post AR invoice', 'slug' => 'ar.post'],
            ['name' => 'Receive AR payment', 'slug' => 'ar.receive'],
            ['name' => 'View payments', 'slug' => 'payments.view'],
            ['name' => 'View banking', 'slug' => 'banking.view'],
            ['name' => 'View expense', 'slug' => 'expense.view'],
            ['name' => 'Create expense', 'slug' => 'expense.create'],
            ['name' => 'Submit expense', 'slug' => 'expense.submit'],
            ['name' => 'Approve expense', 'slug' => 'expense.approve'],
            ['name' => 'Post expense', 'slug' => 'expense.post'],
            ['name' => 'Pay expense', 'slug' => 'expense.pay'],
            ['name' => 'View timesheet', 'slug' => 'timesheet.view'],
            ['name' => 'Create timesheet', 'slug' => 'timesheet.create'],
            ['name' => 'Update timesheet', 'slug' => 'timesheet.update'],
            ['name' => 'Submit timesheet', 'slug' => 'timesheet.submit'],
            ['name' => 'Approve timesheet', 'slug' => 'timesheet.approve'],
            ['name' => 'View fixed asset', 'slug' => 'asset.view'],
            ['name' => 'Create fixed asset', 'slug' => 'asset.create'],
            ['name' => 'Capitalize fixed asset', 'slug' => 'asset.capitalize'],
            ['name' => 'Depreciate fixed asset', 'slug' => 'asset.depreciate'],
            ['name' => 'Transfer fixed asset', 'slug' => 'asset.transfer'],
            ['name' => 'Dispose fixed asset', 'slug' => 'asset.dispose'],
        ];

        $permissions = collect($permissionsList)->mapWithKeys(fn ($permission) => [
            $permission['slug'] => Permission::updateOrCreate(
            ['slug' => $permission['slug']],
            ['name' => $permission['name']],
            ),
        ]);

        $roles->get('super-admin')?->permissions()->sync($permissions->pluck('id'));
        $roles->get('admin-system-owner')?->permissions()->sync($permissions->pluck('id'));
        $roles->get('finance-manager')?->permissions()->sync($permissions->pluck('id'));
        $roles->get('finance')?->permissions()->sync($permissions->only([
            'dashboard.view',
            'master-data.view',
            'master-data.export',
            'accounting.view',
            'accounting.coa.view',
            'accounting.journal.view',
            'accounting.journal.create',
            'accounting.journal.update',
            'accounting.journal.submit',
            'budget.view',
            'budget.validate',
            'tax.view',
            'tax.manage',
            'reports.view',
            'audit.view',
            'ap.view',
            'ap.post',
            'ap.pay',
            'ar.view',
            'ar.create',
            'ar.post',
            'ar.receive',
            'payments.view',
            'banking.view',
            'expense.view',
            'expense.create',
            'expense.submit',
            'expense.approve',
            'expense.post',
            'expense.pay',
            'timesheet.view',
            'timesheet.create',
            'timesheet.update',
            'timesheet.submit',
            'timesheet.approve',
            'asset.view',
            'asset.create',
            'asset.capitalize',
            'asset.depreciate',
            'asset.transfer',
            'asset.dispose',
        ])->pluck('id'));
        $roles->get('budget-holder')?->permissions()->sync($permissions->only([
            'dashboard.view',
            'funding-projects.view',
            'budget.view',
            'budget.validate',
            'expenses-approvals.view',
            'expenses.approve',
            'reports.view',
            'timesheet.view',
            'timesheet.approve',
        ])->pluck('id'));
        $roles->get('manager')?->permissions()->sync($permissions->only([
            'dashboard.view',
            'funding-projects.view',
            'budget.view',
            'budget.validate',
            'expenses-approvals.view',
            'expenses.approve',
            'reports.view',
            'timesheet.view',
            'timesheet.approve',
        ])->pluck('id'));
        $roles->get('procurement')?->permissions()->sync($permissions->only([
            'dashboard.view',
            'master-data.view',
            'funding-projects.view',
            'budget.view',
            'budget.validate',
            'procurement.pr.view',
            'procurement.pr.create',
            'procurement.pr.update',
            'procurement.pr.submit',
            'procurement.pr.approve',
            'procurement.rfq.view',
            'procurement.rfq.create',
            'procurement.cba.view',
            'procurement.cba.create',
            'procurement.cba.approve',
            'procurement.po.view',
            'procurement.po.create',
            'procurement.po.approve',
            'procurement.grn.view',
            'procurement.grn.create',
            'procurement.invoice.view',
            'procurement.invoice.create',
            'procurement.invoice.match',
            'reports.view',
        ])->pluck('id'));
        $roles->get('staff')?->permissions()->sync($permissions->only([
            'dashboard.view',
            'expenses-approvals.view',
            'timesheet.view',
            'timesheet.create',
            'timesheet.update',
            'timesheet.submit',
        ])->pluck('id'));

        $approverPermissions = [
            'dashboard.view',
            'funding-projects.view',
            'budget.view',
            'budget.validate',
            'expenses-approvals.view',
            'expenses.approve',
            'expense.view',
            'procurement.pr.view',
            'procurement.pr.approve',
            'reports.view',
            'timesheet.view',
            'timesheet.approve',
        ];
        $roles->get('project-manager')?->permissions()->sync($permissions->only($approverPermissions)->pluck('id'));
        $roles->get('finance-officer')?->permissions()->sync($permissions->only($approverPermissions)->pluck('id'));
        $roles->get('executive-director')?->permissions()->sync($permissions->only($approverPermissions)->pluck('id'));
        $roles->get('board-director')?->permissions()->sync($permissions->only($approverPermissions)->pluck('id'));

        $menuDefinitions = [
            ['title' => 'Dashboard', 'slug' => 'dashboard', 'path' => '/dashboard', 'icon' => 'dashboard', 'sort_order' => 10],
            ['title' => 'Master Data', 'slug' => 'master-data', 'path' => '/master-data', 'icon' => 'database', 'sort_order' => 15, 'children' => [
                ['title' => 'Organization & Structure', 'slug' => 'master-organization-structure', 'path' => '/master-data', 'sort_order' => 16, 'children' => [
                    ['title' => 'Organizations', 'slug' => 'master-organizations', 'path' => '/master-data/organizations', 'sort_order' => 161],
                    ['title' => 'Office Locations', 'slug' => 'master-office-locations', 'path' => '/master-data/office-locations', 'sort_order' => 162],
                    ['title' => 'Departments', 'slug' => 'master-departments', 'path' => '/master-data/departments', 'sort_order' => 163],
                    ['title' => 'Cost Centers', 'slug' => 'master-cost-centers', 'path' => '/master-data/cost-centers', 'sort_order' => 164],
                    ['title' => 'Positions', 'slug' => 'master-positions', 'path' => '/master-data/positions', 'sort_order' => 165],
                    ['title' => 'Staff / Employees', 'slug' => 'master-employees', 'path' => '/master-data/employees', 'sort_order' => 166],
                ]],
                ['title' => 'Finance & Accounting', 'slug' => 'master-finance-accounting', 'path' => '/master-data', 'sort_order' => 17, 'children' => [
                    ['title' => 'Currencies', 'slug' => 'master-currencies', 'path' => '/master-data/currencies', 'sort_order' => 171],
                    ['title' => 'Exchange Rates', 'slug' => 'master-exchange-rates', 'path' => '/master-data/exchange-rates', 'sort_order' => 172],
                    ['title' => 'Fiscal Years', 'slug' => 'master-fiscal-years', 'path' => '/master-data/fiscal-years', 'sort_order' => 173],
                    ['title' => 'Accounting Periods', 'slug' => 'master-accounting-periods', 'path' => '/master-data/accounting-periods', 'sort_order' => 174],
                    ['title' => 'Chart of Accounts', 'slug' => 'master-chart-of-accounts', 'path' => '/master-data/chart-of-accounts', 'sort_order' => 175],
                    ['title' => 'Account Categories', 'slug' => 'master-account-categories', 'path' => '/master-data/account-categories', 'sort_order' => 176],
                    ['title' => 'Taxes', 'slug' => 'master-taxes', 'path' => '/master-data/taxes', 'sort_order' => 176],
                    ['title' => 'Bank Accounts', 'slug' => 'master-bank-accounts', 'path' => '/master-data/bank-accounts', 'sort_order' => 177],
                    ['title' => 'Petty Cashes', 'slug' => 'master-petty-cashes', 'path' => '/master-data/petty-cashes', 'sort_order' => 178],
                    ['title' => 'Payment Methods', 'slug' => 'master-payment-methods', 'path' => '/master-data/payment-methods', 'sort_order' => 179],
                ]],
                ['title' => 'Funding & Projects', 'slug' => 'master-funding-projects', 'path' => '/master-data', 'sort_order' => 18, 'children' => [
                    ['title' => 'Funding Sources', 'slug' => 'master-funding-sources', 'path' => '/master-data/funding-sources', 'sort_order' => 181],
                    ['title' => 'Donors', 'slug' => 'master-donors', 'path' => '/master-data/donors', 'sort_order' => 182],
                    ['title' => 'Grant Agreements', 'slug' => 'master-grant-agreements', 'path' => '/master-data/grant-agreements', 'sort_order' => 183],
                    ['title' => 'Programs', 'slug' => 'master-programs', 'path' => '/master-data/programs', 'sort_order' => 184],
                    ['title' => 'Projects', 'slug' => 'master-projects', 'path' => '/master-data/projects', 'sort_order' => 185],
                    ['title' => 'Activities', 'slug' => 'master-activities', 'path' => '/master-data/activities', 'sort_order' => 186],
                    ['title' => 'Beneficiary Partners', 'slug' => 'master-beneficiary-partners', 'path' => '/master-data/beneficiary-partners', 'sort_order' => 187],
                ]],
                ['title' => 'Budget & Reporting', 'slug' => 'master-budget-reporting', 'path' => '/master-data', 'sort_order' => 19, 'children' => [
                    ['title' => 'Reporting Dimensions', 'slug' => 'master-reporting-dimensions', 'path' => '/master-data/reporting-dimensions', 'sort_order' => 191],
                    ['title' => 'Unit of Measures', 'slug' => 'master-unit-of-measures', 'path' => '/master-data/unit-of-measures', 'sort_order' => 192],
                    ['title' => 'Budget Categories', 'slug' => 'master-budget-categories', 'path' => '/master-data/budget-categories', 'sort_order' => 193],
                    ['title' => 'Budget Lines', 'slug' => 'master-budget-lines', 'path' => '/master-data/budget-lines', 'sort_order' => 194],
                ]],
                ['title' => 'Expenses & Assets', 'slug' => 'master-expenses-assets', 'path' => '/master-data', 'sort_order' => 20, 'children' => [
                    ['title' => 'Expense Categories', 'slug' => 'master-expense-categories', 'path' => '/master-data/expense-categories', 'sort_order' => 201],
                    ['title' => 'Document Types', 'slug' => 'master-document-types', 'path' => '/master-data/document-types', 'sort_order' => 202],
                    ['title' => 'Asset Categories', 'slug' => 'master-asset-categories', 'path' => '/master-data/asset-categories', 'sort_order' => 203],
                ]],
                ['title' => 'Procurement', 'slug' => 'master-procurement', 'path' => '/master-data/vendors', 'sort_order' => 21, 'children' => [
                    ['title' => 'Vendors / Suppliers / Consultants', 'slug' => 'master-vendors', 'path' => '/master-data/vendors', 'sort_order' => 211],
                    ['title' => 'Vendor Categories', 'slug' => 'master-vendor-categories', 'path' => '/master-data/vendor-categories', 'sort_order' => 212],
                    ['title' => 'Items / Services', 'slug' => 'master-procurement-items', 'path' => '/master-data/procurement-items', 'sort_order' => 213],
                    ['title' => 'Unit of Measure', 'slug' => 'master-unit-of-measures-procurement', 'path' => '/master-data/unit-of-measures', 'sort_order' => 214],
                    ['title' => 'Procurement Categories', 'slug' => 'master-procurement-categories', 'path' => '/master-data/procurement-categories', 'sort_order' => 215],
                ]],
            ]],
            ['title' => 'Donor & Grant', 'slug' => 'funding-projects', 'path' => '/funding-projects/donor-grant', 'icon' => 'briefcase', 'sort_order' => 20, 'children' => [
                ['title' => 'Dashboard', 'slug' => 'funding-donor-grant', 'path' => '/funding-projects/donor-grant', 'sort_order' => 21],
                ['title' => 'Donors', 'slug' => 'funding-donors', 'path' => '/master-data/donors', 'sort_order' => 22],
                ['title' => 'Programs / Projects', 'slug' => 'funding-program-project', 'path' => '/funding-projects/program-project', 'sort_order' => 23],
                ['title' => 'Grants', 'slug' => 'funding-grants', 'path' => '/master-data/grant-agreements', 'sort_order' => 24],
                ['title' => 'Budget', 'slug' => 'funding-budget', 'path' => '/funding-projects/budget', 'sort_order' => 25],
                ['title' => 'Budget Monitoring', 'slug' => 'funding-budget-monitoring', 'path' => '/funding-projects/budget-monitoring', 'sort_order' => 26],
                ['title' => 'Grant Reporting', 'slug' => 'funding-grant-reporting', 'path' => '/reports/custom', 'sort_order' => 27],
            ]],
            ['title' => 'Expenses & Approvals', 'slug' => 'expenses-approvals', 'path' => '/expenses-approvals', 'icon' => 'wallet', 'sort_order' => 30, 'children' => [
                ['title' => 'Dashboard', 'slug' => 'expenses-dashboard', 'path' => '/expenses-approvals/expenses', 'sort_order' => 31],
                ['title' => 'Expense Requests', 'slug' => 'expenses', 'path' => '/expenses-approvals/expenses', 'sort_order' => 32],
                ['title' => 'Cash Advance', 'slug' => 'cash-advance', 'path' => '/expenses-approvals/cash-advance', 'sort_order' => 32],
                ['title' => 'Reimbursement', 'slug' => 'reimbursement', 'path' => '/expenses-approvals/reimbursement', 'sort_order' => 33],
                ['title' => 'Settlement', 'slug' => 'expense-settlement', 'path' => '/expenses-approvals/cash-advance', 'sort_order' => 34],
                ['title' => 'Approval Center', 'slug' => 'approvals', 'path' => '/expenses-approvals/approvals', 'sort_order' => 35],
                ['title' => 'Finance Verification', 'slug' => 'expense-finance-verification', 'path' => '/expenses-approvals/approvals', 'sort_order' => 36],
                ['title' => 'Payment Processing', 'slug' => 'expense-payment-processing', 'path' => '/expenses-approvals/expenses', 'sort_order' => 37],
                ['title' => 'Expense Monitoring', 'slug' => 'expense-monitoring', 'path' => '/reports/custom', 'sort_order' => 38],
            ]],
            ['title' => 'Accounting', 'slug' => 'accounting', 'path' => '/accounting', 'icon' => 'accounting', 'sort_order' => 40, 'children' => [
                ['title' => 'Dashboard', 'slug' => 'accounting-dashboard', 'path' => '/dashboard', 'sort_order' => 41],
                ['title' => 'Chart of Accounts', 'slug' => 'accounting-chart-of-accounts', 'path' => '/master-data/chart-of-accounts', 'sort_order' => 42],
                ['title' => 'Journal', 'slug' => 'journal', 'path' => '/accounting/journal', 'sort_order' => 43],
                ['title' => 'General Ledger', 'slug' => 'accounting-general-ledger', 'path' => '/reports', 'sort_order' => 44],
                ['title' => 'Accounts Payable', 'slug' => 'accounts-payable', 'path' => '/accounting/accounts-payable', 'sort_order' => 45],
                ['title' => 'Accounts Receivable', 'slug' => 'accounts-receivable', 'path' => '/accounting/accounts-receivable', 'sort_order' => 46],
                ['title' => 'Banking', 'slug' => 'accounting-banking', 'path' => '/accounting/bank-reconciliation', 'sort_order' => 47],
                ['title' => 'Bank Reconciliation', 'slug' => 'bank-reconciliation', 'path' => '/accounting/bank-reconciliation', 'sort_order' => 48],
                ['title' => 'Fixed Assets', 'slug' => 'fixed-assets', 'path' => '/accounting/fixed-assets', 'sort_order' => 49],
                ['title' => 'Period Closing', 'slug' => 'accounting-period-closing', 'path' => '/master-data/accounting-periods', 'sort_order' => 50],
            ]],
            ['title' => 'Taxes', 'slug' => 'tax-calculator', 'path' => '/accounting/tax', 'icon' => 'calculator', 'sort_order' => 45, 'children' => [
                ['title' => 'Dashboard', 'slug' => 'tax-dashboard', 'path' => '/accounting/tax', 'sort_order' => 451],
                ['title' => 'Tax Transactions', 'slug' => 'tax-transactions', 'path' => '/accounting/tax', 'sort_order' => 452],
                ['title' => 'PPh', 'slug' => 'tax-pph', 'path' => '/accounting/tax', 'sort_order' => 453],
                ['title' => 'VAT / PPN', 'slug' => 'tax-vat', 'path' => '/accounting/tax', 'sort_order' => 454],
                ['title' => 'e-Bupot', 'slug' => 'tax-ebupot', 'path' => '/accounting/tax', 'sort_order' => 455],
                ['title' => 'e-Faktur', 'slug' => 'tax-efaktur', 'path' => '/accounting/tax', 'sort_order' => 456],
                ['title' => 'Tax Calendar', 'slug' => 'tax-calendar', 'path' => '/accounting/tax', 'sort_order' => 457],
                ['title' => 'Tax Reports', 'slug' => 'tax-reports', 'path' => '/accounting/tax', 'sort_order' => 458],
            ]],
            ['title' => 'Timesheet', 'slug' => 'timesheet', 'path' => '/expenses-approvals/timesheet', 'icon' => 'clock', 'sort_order' => 46, 'children' => [
                ['title' => 'Dashboard', 'slug' => 'timesheet-dashboard', 'path' => '/expenses-approvals/timesheet', 'sort_order' => 461],
                ['title' => 'My Timesheet', 'slug' => 'timesheet-my', 'path' => '/expenses-approvals/timesheet', 'sort_order' => 462],
                ['title' => 'Team Timesheet', 'slug' => 'timesheet-team', 'path' => '/expenses-approvals/timesheet', 'sort_order' => 463],
                ['title' => 'Project Timesheet', 'slug' => 'timesheet-project', 'path' => '/expenses-approvals/timesheet', 'sort_order' => 464],
                ['title' => 'Approval', 'slug' => 'timesheet-approval', 'path' => '/expenses-approvals/timesheet', 'sort_order' => 465],
                ['title' => 'Reports', 'slug' => 'timesheet-reports', 'path' => '/reports/custom', 'sort_order' => 466],
            ]],
            ['title' => 'Procurement', 'slug' => 'procurement', 'path' => '/procurement', 'icon' => 'briefcase', 'sort_order' => 49, 'children' => [
                ['title' => 'Dashboard', 'slug' => 'procurement-dashboard', 'path' => '/procurement/purchase-requests', 'sort_order' => 490],
                ['title' => 'Purchase Request', 'slug' => 'procurement-purchase-request', 'path' => '/procurement/purchase-requests', 'sort_order' => 491],
                ['title' => 'RFQ & CBA', 'slug' => 'procurement-rfq-cba', 'path' => '/procurement/rfq-cba', 'sort_order' => 492],
                ['title' => 'Vendors', 'slug' => 'procurement-vendors', 'path' => '/master-data/vendors', 'sort_order' => 494],
                ['title' => 'Purchase Orders', 'slug' => 'procurement-purchase-orders', 'path' => '/procurement/purchase-orders', 'sort_order' => 495],
                ['title' => 'Contracts', 'slug' => 'procurement-contracts', 'path' => '/procurement/purchase-orders', 'sort_order' => 495],
                ['title' => 'Supplier Contract Notification', 'slug' => 'procurement-scn', 'path' => '/procurement/scn', 'sort_order' => 496],
                ['title' => 'Goods Receipts', 'slug' => 'procurement-goods-receipts', 'path' => '/procurement/goods-receipts', 'sort_order' => 496],
                ['title' => 'Supplier Invoices', 'slug' => 'procurement-supplier-invoices', 'path' => '/procurement/supplier-invoices', 'sort_order' => 497],
                ['title' => 'Waiver & Justification', 'slug' => 'procurement-waiver', 'path' => '/procurement/purchase-requests', 'sort_order' => 498],
            ]],
            ['title' => 'Administration', 'slug' => 'hr-administration', 'path' => '/administration', 'icon' => 'admin', 'sort_order' => 55, 'children' => [
                ['title' => 'Staff', 'slug' => 'hr-employees', 'path' => '/master-data/employees', 'sort_order' => 551],
                ['title' => 'Organization', 'slug' => 'hr-organization', 'path' => '/master-data/organizations', 'sort_order' => 552],
                ['title' => 'Department', 'slug' => 'hr-departments', 'path' => '/master-data/departments', 'sort_order' => 553],
                ['title' => 'Documents', 'slug' => 'hr-documents', 'path' => '/master-data/document-types', 'sort_order' => 554],
                ['title' => 'Contracts', 'slug' => 'hr-contracts', 'path' => '/procurement/purchase-orders', 'sort_order' => 555],
                ['title' => 'Role Access', 'slug' => 'hr-role-access', 'path' => '/administration/role-access', 'sort_order' => 554],
                ['title' => 'Approval Matrix', 'slug' => 'hr-approval-matrix', 'path' => '/administration/approval-matrix', 'sort_order' => 555],
                ['title' => 'Audit Log', 'slug' => 'hr-audit-log', 'path' => '/administration/audit-logs', 'sort_order' => 556],
                ['title' => 'Master Menu', 'slug' => 'hr-master-menu', 'path' => '/administration/master-menu', 'sort_order' => 557],
            ]],
            ['title' => 'Reports', 'slug' => 'reports', 'path' => '/reports', 'icon' => 'reports', 'sort_order' => 50, 'children' => [
                ['title' => 'Financial Reports', 'slug' => 'reports-summary', 'path' => '/reports', 'sort_order' => 501],
                ['title' => 'Budget Reports', 'slug' => 'reports-budget', 'path' => '/reports', 'sort_order' => 502],
                ['title' => 'Donor / Grant Reports', 'slug' => 'reports-donor-grant', 'path' => '/reports/custom', 'sort_order' => 503],
                ['title' => 'Project Reports', 'slug' => 'reports-project', 'path' => '/reports/custom', 'sort_order' => 504],
                ['title' => 'Procurement Reports', 'slug' => 'reports-procurement', 'path' => '/reports/custom', 'sort_order' => 505],
                ['title' => 'Tax Reports', 'slug' => 'reports-tax', 'path' => '/accounting/tax', 'sort_order' => 506],
                ['title' => 'Management Reports', 'slug' => 'reports-custom', 'path' => '/reports/custom', 'sort_order' => 507],
                ['title' => 'Expense Forecast', 'slug' => 'reports-forecast', 'path' => '/reports/forecast', 'sort_order' => 508],
            ]],
            ['title' => 'Settings', 'slug' => 'settings', 'path' => '/settings', 'icon' => 'settings', 'sort_order' => 70, 'children' => [
                ['title' => 'Users', 'slug' => 'settings-users', 'path' => '/settings', 'sort_order' => 701],
                ['title' => 'Roles & Permissions', 'slug' => 'settings-roles-permissions', 'path' => '/administration/role-access', 'sort_order' => 702],
                ['title' => 'Approval Workflow', 'slug' => 'settings-approval-workflow', 'path' => '/administration/approval-matrix', 'sort_order' => 703],
                ['title' => 'System Settings', 'slug' => 'settings-system', 'path' => '/settings', 'sort_order' => 704],
                ['title' => 'Integrations', 'slug' => 'settings-integrations', 'path' => '/settings', 'sort_order' => 705],
                ['title' => 'Notifications', 'slug' => 'settings-notifications', 'path' => '/settings', 'sort_order' => 706],
            ]],
        ];

        $menus = collect();

        $persistMenu = function (array $definition, ?int $parentId = null) use (&$persistMenu, $menus): void {
            $children = $definition['children'] ?? [];
            unset($definition['children']);
            $menu = Menu::updateOrCreate(
                ['slug' => $definition['slug']],
                [...$definition, 'parent_id' => $parentId, 'is_active' => true],
            );
            $menus->push($menu);
            foreach ($children as $childDefinition) {
                $persistMenu($childDefinition, $menu->id);
            }
        };
        foreach ($menuDefinitions as $definition) {
            $persistMenu($definition);
        }

        // Approval Matrix is managed from Administration now, not Master Data.
        $legacyApprovalMatrixMenu = Menu::where('slug', 'master-approval-matrices')->first();
        if ($legacyApprovalMatrixMenu) {
            $legacyApprovalMatrixMenu->update(['is_active' => false]);
            $roles->each(fn (Role $role) => $role->menus()->detach($legacyApprovalMatrixMenu->id));
        }

        // Tax is now a dedicated top-level module. Keep the old Accounting > Tax
        // record for audit history, but prevent a duplicate sidebar entry.
        $legacyTaxMenu = Menu::where('slug', 'accounting-tax')->first();
        if ($legacyTaxMenu) {
            $legacyTaxMenu->update(['is_active' => false]);
            $roles->each(fn (Role $role) => $role->menus()->detach($legacyTaxMenu->id));
        }

        $auditMenu = $menus->firstWhere('slug', 'audit-log');
        $regularMenuIds = $menus->reject(fn (Menu $menu) => $menu->slug === 'audit-log')->pluck('id');
        $auditPermissionId = $permissions->get('audit.view')?->id;

        $roles->each(function (Role $role) use ($regularMenuIds, $auditMenu, $auditPermissionId) {
            $role->menus()->syncWithoutDetaching($regularMenuIds);

            if ($auditMenu && $auditPermissionId && $role->permissions()->where('permissions.id', $auditPermissionId)->exists()) {
                $role->menus()->syncWithoutDetaching([$auditMenu->id]);
            } elseif ($auditMenu) {
                $role->menus()->detach($auditMenu->id);
            }
        });

        User::updateOrCreate(
            ['email' => 'admin@kaoemtelapak.test'],
            [
                'name' => 'Admin Kaoem Telapak',
                'role_id' => $roles->get('super-admin')?->id,
                'password' => Hash::make('password123'),
            ],
        );

        // Run Master Data Seeder
        $this->call(MasterDataSeeder::class);
        $this->call(BudgetAlertThresholdSeeder::class);
    }
}
