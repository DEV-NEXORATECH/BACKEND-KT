<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command?->info('Seeding RBAC roles, permissions, menus, and default admin...');

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
                ['title' => 'Organization & Structure', 'slug' => 'master-organization-structure', 'path' => '/master-data/organizations', 'sort_order' => 16, 'children' => [
                    ['title' => 'Organizations', 'slug' => 'master-organizations', 'path' => '/master-data/organizations', 'sort_order' => 161],
                    ['title' => 'Office Locations', 'slug' => 'master-office-locations', 'path' => '/master-data/office-locations', 'sort_order' => 162],
                    ['title' => 'Departments', 'slug' => 'master-departments', 'path' => '/master-data/departments', 'sort_order' => 163],
                    ['title' => 'Positions', 'slug' => 'master-positions', 'path' => '/master-data/positions', 'sort_order' => 165],
                    ['title' => 'Staff / Employees', 'slug' => 'master-employees', 'path' => '/master-data/employees', 'sort_order' => 166],
                ]],
                ['title' => 'Finance & Accounting', 'slug' => 'master-finance-accounting', 'path' => '/master-data/chart-of-accounts', 'sort_order' => 17, 'children' => [
                    ['title' => 'Chart of Accounts', 'slug' => 'master-chart-of-accounts', 'path' => '/master-data/chart-of-accounts', 'sort_order' => 175],
                    ['title' => 'Account Categories', 'slug' => 'master-account-categories', 'path' => '/master-data/account-categories', 'sort_order' => 176],
                    ['title' => 'Bank Accounts', 'slug' => 'master-bank-accounts', 'path' => '/master-data/bank-accounts', 'sort_order' => 177],
                    ['title' => 'Payment Methods', 'slug' => 'master-payment-methods', 'path' => '/master-data/payment-methods', 'sort_order' => 179],
                    ['title' => 'Fx Rates', 'slug' => 'master-fx-rates', 'path' => '/master-data/exchange-rates', 'sort_order' => 172],
                    ['title' => 'Currencies', 'slug' => 'master-currencies', 'path' => '/master-data/currencies', 'sort_order' => 171],
                    ['title' => 'Fiscal Years', 'slug' => 'master-fiscal-years', 'path' => '/master-data/fiscal-years', 'sort_order' => 173],
                    ['title' => 'Accounting Periods', 'slug' => 'master-accounting-periods', 'path' => '/master-data/accounting-periods', 'sort_order' => 174],
                    ['title' => 'Tax Master', 'slug' => 'master-taxes', 'path' => '/master-data/taxes', 'sort_order' => 176],
                ]],
                ['title' => 'Funding & Projects', 'slug' => 'master-funding-projects', 'path' => '/master-data/funding-sources', 'sort_order' => 18, 'children' => [
                    ['title' => 'Donors', 'slug' => 'master-donors', 'path' => '/master-data/donors', 'sort_order' => 182],
                    ['title' => 'Grant/Agreements', 'slug' => 'master-grant-agreements', 'path' => '/master-data/grant-agreements', 'sort_order' => 183],
                    ['title' => 'Programs', 'slug' => 'master-programs', 'path' => '/master-data/programs', 'sort_order' => 184],
                    ['title' => 'Projects', 'slug' => 'master-projects', 'path' => '/master-data/projects', 'sort_order' => 185],
                    ['title' => 'Activities', 'slug' => 'master-activities', 'path' => '/master-data/activities', 'sort_order' => 186],
                    ['title' => 'Budget Codes', 'slug' => 'master-budget-codes', 'path' => '/master-data/budget-lines', 'sort_order' => 187],
                    ['title' => 'Sources of Fund (SoF)', 'slug' => 'master-sof', 'path' => '/master-data/funding-sources', 'sort_order' => 188],
                ]],
                ['title' => 'Budget & Reporting', 'slug' => 'master-budget-reporting', 'path' => '/master-data/budget-categories', 'sort_order' => 19, 'children' => [
                    ['title' => 'Budget Categories', 'slug' => 'master-budget-categories', 'path' => '/master-data/budget-categories', 'sort_order' => 193],
                    ['title' => 'Budget Templates / Lines', 'slug' => 'master-budget-templates', 'path' => '/master-data/budget-lines', 'sort_order' => 194],
                    ['title' => 'Reporting Categories', 'slug' => 'master-reporting-categories', 'path' => '/master-data/reporting-categories', 'sort_order' => 195],
                    ['title' => 'Reporting Dimensions', 'slug' => 'master-reporting-dimensions', 'path' => '/master-data/reporting-dimensions', 'sort_order' => 191],
                ]],
                ['title' => 'Expenses & Assets', 'slug' => 'master-expenses-assets', 'path' => '/master-data/expense-categories', 'sort_order' => 20, 'children' => [
                    ['title' => 'Expense Categories', 'slug' => 'master-expense-categories', 'path' => '/master-data/expense-categories', 'sort_order' => 201],
                    ['title' => 'Document / Expense Types', 'slug' => 'master-expense-types', 'path' => '/master-data/document-types', 'sort_order' => 202],
                    ['title' => 'Asset Categories', 'slug' => 'master-asset-categories', 'path' => '/master-data/asset-categories', 'sort_order' => 203],
                ]],
                ['title' => 'Procurement', 'slug' => 'master-procurement', 'path' => '/master-data/vendors', 'sort_order' => 21, 'children' => [
                    ['title' => 'Vendors/Suppliers/Consultants', 'slug' => 'master-vendors', 'path' => '/master-data/vendors', 'sort_order' => 211],
                    ['title' => 'Vendor Categories', 'slug' => 'master-vendor-categories', 'path' => '/master-data/vendor-categories', 'sort_order' => 212],
                    ['title' => 'Items/Services', 'slug' => 'master-items-services', 'path' => '/master-data/procurement-items', 'sort_order' => 213],
                    ['title' => 'Unit of Measures', 'slug' => 'master-unit-of-measures', 'path' => '/master-data/unit-of-measures', 'sort_order' => 214],
                    ['title' => 'Procurement Categories', 'slug' => 'master-procurement-categories', 'path' => '/master-data/procurement-categories', 'sort_order' => 215],
                ]],
            ]],
            ['title' => 'Donor & Grant', 'slug' => 'donor-grant', 'path' => '/donor-grant', 'icon' => 'briefcase', 'sort_order' => 20, 'children' => [
                ['title' => 'Dashboard', 'slug' => 'donor-grant-dashboard', 'path' => '/donor-grant/dashboard', 'sort_order' => 21],
                ['title' => 'Donors', 'slug' => 'donor-grant-donors', 'path' => '/donor-grant/donors', 'sort_order' => 22],
                ['title' => 'Programs / Projects', 'slug' => 'donor-grant-programs-projects', 'path' => '/donor-grant/programs-projects', 'sort_order' => 23],
                ['title' => 'Grants', 'slug' => 'donor-grant-grants', 'path' => '/donor-grant/grants', 'sort_order' => 24],
                ['title' => 'Budget', 'slug' => 'donor-grant-budget', 'path' => '/donor-grant/budget', 'sort_order' => 25],
                ['title' => 'Budget Monitoring', 'slug' => 'donor-grant-budget-monitoring', 'path' => '/donor-grant/budget-monitoring', 'sort_order' => 26],
                ['title' => 'Grant Reporting', 'slug' => 'donor-grant-reporting', 'path' => '/donor-grant/reporting', 'sort_order' => 27],
            ]],
            ['title' => 'Expenses & Approvals', 'slug' => 'expenses-approvals', 'path' => '/expenses-approvals', 'icon' => 'wallet', 'sort_order' => 30, 'children' => [
                ['title' => 'Dashboard', 'slug' => 'expenses-dashboard', 'path' => '/expenses-approvals/dashboard', 'sort_order' => 30],
                ['title' => 'Expense Requests', 'slug' => 'expense-requests', 'path' => '/expenses-approvals/requests', 'sort_order' => 31],
                ['title' => 'Reimbursements', 'slug' => 'reimbursements', 'path' => '/expenses-approvals/reimbursements', 'sort_order' => 32],
                ['title' => 'Cash Advances', 'slug' => 'cash-advances', 'path' => '/expenses-approvals/cash-advances', 'sort_order' => 33],
                ['title' => 'Settlement', 'slug' => 'settlement', 'path' => '/expenses-approvals/settlement', 'sort_order' => 34],
                ['title' => 'Approval Center', 'slug' => 'approval-center', 'path' => '/expenses-approvals/approvals', 'sort_order' => 35],
                ['title' => 'Finance Verification', 'slug' => 'finance-verification', 'path' => '/expenses-approvals/finance-verification', 'sort_order' => 36],
                ['title' => 'Payment Processing', 'slug' => 'payment-processing', 'path' => '/expenses-approvals/payment-processing', 'sort_order' => 37],
                ['title' => 'Expense Monitoring', 'slug' => 'expense-monitoring', 'path' => '/expenses-approvals/monitoring', 'sort_order' => 38],
            ]],
            ['title' => 'Accounting', 'slug' => 'accounting', 'path' => '/accounting', 'icon' => 'accounting', 'sort_order' => 40, 'children' => [
                ['title' => 'Dashboard', 'slug' => 'accounting-dashboard', 'path' => '/accounting/dashboard', 'sort_order' => 40],
                ['title' => 'Chart of Accounts', 'slug' => 'accounting-chart-of-accounts', 'path' => '/accounting/chart-of-accounts', 'sort_order' => 41],
                ['title' => 'Journal', 'slug' => 'journal', 'path' => '/accounting/journal', 'sort_order' => 42],
                ['title' => 'General Ledger', 'slug' => 'general-ledger', 'path' => '/accounting/general-ledger', 'sort_order' => 43],
                ['title' => 'Accounts Payable', 'slug' => 'accounts-payable', 'path' => '/accounting/accounts-payable', 'sort_order' => 44],
                ['title' => 'Accounts Receivable', 'slug' => 'accounts-receivable', 'path' => '/accounting/accounts-receivable', 'sort_order' => 45],
                ['title' => 'Banking', 'slug' => 'banking', 'path' => '/accounting/banking', 'sort_order' => 46],
                ['title' => 'Bank Reconciliation', 'slug' => 'bank-reconciliation', 'path' => '/accounting/bank-reconciliation', 'sort_order' => 47],
                ['title' => 'Fixed Assets', 'slug' => 'fixed-assets', 'path' => '/accounting/fixed-assets', 'sort_order' => 48],
                ['title' => 'Period Closing', 'slug' => 'period-closing', 'path' => '/accounting/period-closing', 'sort_order' => 49],
            ]],
            ['title' => 'Procurement', 'slug' => 'procurement', 'path' => '/procurement', 'icon' => 'briefcase', 'sort_order' => 49, 'children' => [
                ['title' => 'Dashboard', 'slug' => 'procurement-dashboard', 'path' => '/procurement/dashboard', 'sort_order' => 490],
                ['title' => 'Purchase Request', 'slug' => 'procurement-purchase-request', 'path' => '/procurement/purchase-requests', 'sort_order' => 491],
                ['title' => 'RFQ & CBA', 'slug' => 'procurement-rfq-cba', 'path' => '/procurement/rfq-cba', 'sort_order' => 492],
                ['title' => 'Vendors', 'slug' => 'procurement-vendors', 'path' => '/procurement/vendors', 'sort_order' => 493],
                ['title' => 'Purchase Orders', 'slug' => 'procurement-purchase-orders', 'path' => '/procurement/purchase-orders', 'sort_order' => 494],
                ['title' => 'Contracts', 'slug' => 'procurement-contracts', 'path' => '/procurement/contracts', 'sort_order' => 495],
                ['title' => 'Supplier Contract Notification', 'slug' => 'procurement-scn', 'path' => '/procurement/scn', 'sort_order' => 496],
                ['title' => 'Goods Receipts', 'slug' => 'procurement-goods-receipts', 'path' => '/procurement/goods-receipts', 'sort_order' => 497],
                ['title' => 'Supplier Invoices', 'slug' => 'procurement-supplier-invoices', 'path' => '/procurement/supplier-invoices', 'sort_order' => 498],
                ['title' => 'Waiver & Justification', 'slug' => 'procurement-waivers', 'path' => '/procurement/waivers', 'sort_order' => 499],
            ]],
            ['title' => 'Taxes', 'slug' => 'taxes', 'path' => '/taxes', 'icon' => 'calculator', 'sort_order' => 50, 'children' => [
                ['title' => 'Dashboard', 'slug' => 'taxes-dashboard', 'path' => '/taxes/dashboard', 'sort_order' => 501],
                ['title' => 'Tax Setup', 'slug' => 'taxes-setup', 'path' => '/taxes/setup', 'sort_order' => 500],
                ['title' => 'Tax Transactions', 'slug' => 'taxes-transactions', 'path' => '/taxes/transactions', 'sort_order' => 502],
                ['title' => 'PPh', 'slug' => 'taxes-pph', 'path' => '/taxes/pph', 'sort_order' => 503],
                ['title' => 'VAT / PPN', 'slug' => 'taxes-vat', 'path' => '/taxes/vat', 'sort_order' => 504],
                ['title' => 'e-Bupot', 'slug' => 'taxes-ebupot', 'path' => '/taxes/e-bupot', 'sort_order' => 505],
                ['title' => 'e-Faktur', 'slug' => 'taxes-efaktur', 'path' => '/taxes/e-faktur', 'sort_order' => 506],
                ['title' => 'Tax Calendar', 'slug' => 'taxes-calendar', 'path' => '/taxes/calendar', 'sort_order' => 507],
                ['title' => 'Tax Reports', 'slug' => 'taxes-reports', 'path' => '/taxes/reports', 'sort_order' => 508],
            ]],
            ['title' => 'Tax Calculator', 'slug' => 'tax-calculator', 'path' => '/tax-calculator', 'icon' => 'calculator', 'sort_order' => 51],
            ['title' => 'Timesheet', 'slug' => 'timesheet', 'path' => '/timesheet', 'icon' => 'clock', 'sort_order' => 52, 'children' => [
                ['title' => 'Dashboard', 'slug' => 'timesheet-dashboard', 'path' => '/timesheet/dashboard', 'sort_order' => 521],
                ['title' => 'My Timesheet', 'slug' => 'timesheet-my', 'path' => '/timesheet/my-timesheet', 'sort_order' => 522],
                ['title' => 'Team Timesheet', 'slug' => 'timesheet-team', 'path' => '/timesheet/team-timesheet', 'sort_order' => 523],
                ['title' => 'Project Timesheet', 'slug' => 'timesheet-project', 'path' => '/timesheet/project-timesheet', 'sort_order' => 524],
                ['title' => 'Approval', 'slug' => 'timesheet-approval', 'path' => '/timesheet/approval', 'sort_order' => 525],
                ['title' => 'Reports', 'slug' => 'timesheet-reports', 'path' => '/timesheet/reports', 'sort_order' => 526],
            ]],
            ['title' => 'Reports', 'slug' => 'reports', 'path' => '/reports', 'icon' => 'reports', 'sort_order' => 55, 'children' => [
                ['title' => 'Financial Reports', 'slug' => 'reports-financial', 'path' => '/reports/financial', 'sort_order' => 551],
                ['title' => 'Budget Reports', 'slug' => 'reports-budget', 'path' => '/reports/budget', 'sort_order' => 552],
                ['title' => 'Donor / Grant Reports', 'slug' => 'reports-donor-grant', 'path' => '/reports/donor-grant', 'sort_order' => 553],
                ['title' => 'Project Reports', 'slug' => 'reports-project', 'path' => '/reports/project', 'sort_order' => 554],
                ['title' => 'Procurement Reports', 'slug' => 'reports-procurement', 'path' => '/reports/procurement', 'sort_order' => 555],
                ['title' => 'Tax Reports', 'slug' => 'reports-tax', 'path' => '/reports/tax', 'sort_order' => 556],
                ['title' => 'Management Reports', 'slug' => 'reports-management', 'path' => '/reports/management', 'sort_order' => 557],
            ]],
            ['title' => 'Administration', 'slug' => 'administration', 'path' => '/administration', 'icon' => 'admin', 'sort_order' => 60, 'children' => [
                ['title' => 'Staff', 'slug' => 'admin-staff', 'path' => '/administration/staff', 'sort_order' => 601],
                ['title' => 'Organization', 'slug' => 'admin-organization', 'path' => '/administration/organization', 'sort_order' => 602],
                ['title' => 'Department', 'slug' => 'admin-department', 'path' => '/administration/department', 'sort_order' => 603],
                ['title' => 'Documents', 'slug' => 'admin-documents', 'path' => '/administration/documents', 'sort_order' => 604],
                ['title' => 'Contracts', 'slug' => 'admin-contracts', 'path' => '/administration/contracts', 'sort_order' => 605],
                ['title' => 'Audit Log', 'slug' => 'admin-audit-logs', 'path' => '/administration/audit-logs', 'sort_order' => 606],
            ]],
            ['title' => 'Settings', 'slug' => 'settings', 'path' => '/settings', 'icon' => 'settings', 'sort_order' => 70, 'children' => [
                ['title' => 'Users', 'slug' => 'settings-users', 'path' => '/settings/users', 'sort_order' => 701],
                ['title' => 'Roles & Permissions', 'slug' => 'settings-roles-permissions', 'path' => '/settings/roles-permissions', 'sort_order' => 702],
                ['title' => 'Approval Workflow', 'slug' => 'settings-approval-workflow', 'path' => '/settings/approval-workflow', 'sort_order' => 703],
                ['title' => 'System Settings', 'slug' => 'settings-system', 'path' => '/settings/system', 'sort_order' => 704],
                ['title' => 'Integrations', 'slug' => 'settings-integrations', 'path' => '/settings/integrations', 'sort_order' => 705],
                ['title' => 'Notifications', 'slug' => 'settings-notifications', 'path' => '/settings/notifications', 'sort_order' => 706],
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

        // These legacy direct children made Master Data long and bypassed the
        // six category landing pages. Keep them inactive after every seed run.
        Menu::whereIn('slug', ['coa', 'fiscal', 'currency', 'tax', 'master-fund-grant-management', 'master-ca', 'master-depreciation', 'funding-projects', 'funding-donor-grant', 'funding-program-project', 'funding-budget', 'expenses', 'cash-advance', 'reimbursement', 'approvals', 'accounting-tax', 'recurring-journal', 'hr-administration', 'hr-employees', 'hr-departments', 'hr-office-locations', 'hr-role-access', 'hr-approval-matrix', 'hr-audit-log', 'hr-master-menu', 'reports-summary', 'reports-forecast', 'reports-custom'])
            ->update(['is_active' => false]);

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
                'must_change_password' => true,
            ],
        );

        $this->command?->info('RBAC roles, permissions, menus, and default admin seeded.');

        // Run Master Data Seeder
        $this->call(MasterDataSeeder::class);
        $this->call(BudgetAlertThresholdSeeder::class);
        $this->call(DepartmentSeeder::class);
        $this->call(MasterMenuSeeder::class);
        $this->call(RoleSeeder::class);
        $this->call(UserSeeder::class);

        // RoleSeeder supports standalone deployments, but DatabaseSeeder owns
        // the canonical navigation. Re-apply it last so paths/titles stay in sync.
        foreach ($menuDefinitions as $definition) {
            $persistMenu($definition);
        }
        Menu::whereIn('slug', ['coa', 'fiscal', 'currency', 'tax', 'master-fund-grant-management', 'master-ca', 'master-depreciation', 'funding-projects', 'funding-donor-grant', 'funding-program-project', 'funding-budget', 'expenses', 'cash-advance', 'reimbursement', 'approvals', 'accounting-tax', 'recurring-journal', 'hr-administration', 'hr-employees', 'hr-departments', 'hr-office-locations', 'hr-role-access', 'hr-approval-matrix', 'hr-audit-log', 'hr-master-menu', 'reports-summary', 'reports-forecast', 'reports-custom'])
            ->update(['is_active' => false]);
    }
}
