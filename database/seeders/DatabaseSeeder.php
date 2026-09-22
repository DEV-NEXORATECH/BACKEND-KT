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
            ['name' => 'Create GRN', 'slug' => 'procurement.grn.create'],
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
            'procurement.grn.create',
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

        $menuDefinitions = [
            ['title' => 'Dashboard', 'slug' => 'dashboard', 'path' => '/dashboard', 'icon' => 'dashboard', 'sort_order' => 10],
            ['title' => 'Master Data', 'slug' => 'master-data', 'path' => '/master-data', 'icon' => 'database', 'sort_order' => 15, 'children' => [
                ['title' => 'Organization & Structure', 'slug' => 'master-organization-structure', 'path' => '/master-data', 'sort_order' => 16, 'children' => [
                    ['title' => 'Organizations', 'slug' => 'master-organizations', 'path' => '/master-data/organizations', 'sort_order' => 161],
                    ['title' => 'Office Locations', 'slug' => 'master-office-locations', 'path' => '/master-data/office-locations', 'sort_order' => 162],
                    ['title' => 'Departments', 'slug' => 'master-departments', 'path' => '/master-data/departments', 'sort_order' => 163],
                    ['title' => 'Cost Centers', 'slug' => 'master-cost-centers', 'path' => '/master-data/cost-centers', 'sort_order' => 164],
                    ['title' => 'Employees', 'slug' => 'master-employees', 'path' => '/master-data/employees', 'sort_order' => 165],
                ]],
                ['title' => 'Finance & Accounting', 'slug' => 'master-finance-accounting', 'path' => '/master-data', 'sort_order' => 17, 'children' => [
                    ['title' => 'Currencies', 'slug' => 'master-currencies', 'path' => '/master-data/currencies', 'sort_order' => 171],
                    ['title' => 'Exchange Rates', 'slug' => 'master-exchange-rates', 'path' => '/master-data/exchange-rates', 'sort_order' => 172],
                    ['title' => 'Fiscal Years', 'slug' => 'master-fiscal-years', 'path' => '/master-data/fiscal-years', 'sort_order' => 173],
                    ['title' => 'Accounting Periods', 'slug' => 'master-accounting-periods', 'path' => '/master-data/accounting-periods', 'sort_order' => 174],
                    ['title' => 'Chart of Accounts', 'slug' => 'master-chart-of-accounts', 'path' => '/master-data/chart-of-accounts', 'sort_order' => 175],
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
            ]],
            ['title' => 'Funding & Projects', 'slug' => 'funding-projects', 'path' => '/funding-projects', 'icon' => 'briefcase', 'sort_order' => 20, 'children' => [
                ['title' => 'Donor & Grant', 'slug' => 'funding-donor-grant', 'path' => '/funding-projects/donor-grant', 'sort_order' => 21],
                ['title' => 'Program/Project', 'slug' => 'funding-program-project', 'path' => '/funding-projects/program-project', 'sort_order' => 22],
                ['title' => 'Budget', 'slug' => 'funding-budget', 'path' => '/funding-projects/budget', 'sort_order' => 23],
            ]],
            ['title' => 'Expenses & Approvals', 'slug' => 'expenses-approvals', 'path' => '/expenses-approvals', 'icon' => 'wallet', 'sort_order' => 30, 'children' => [
                ['title' => 'Expenses', 'slug' => 'expenses', 'path' => '/expenses-approvals/expenses', 'sort_order' => 31],
                ['title' => 'Cash Advance', 'slug' => 'cash-advance', 'path' => '/expenses-approvals/cash-advance', 'sort_order' => 32],
                ['title' => 'Reimbursement', 'slug' => 'reimbursement', 'path' => '/expenses-approvals/reimbursement', 'sort_order' => 33],
                ['title' => 'Approvals', 'slug' => 'approvals', 'path' => '/expenses-approvals/approvals', 'sort_order' => 34],
                ['title' => 'Timesheet', 'slug' => 'timesheet', 'path' => '/expenses-approvals/timesheet', 'sort_order' => 35],
            ]],
            ['title' => 'Accounting', 'slug' => 'accounting', 'path' => '/accounting', 'icon' => 'accounting', 'sort_order' => 40, 'children' => [
                ['title' => 'Journal', 'slug' => 'journal', 'path' => '/accounting/journal', 'sort_order' => 41],
                ['title' => 'Chart of Accounts', 'slug' => 'accounting-chart-of-accounts', 'path' => '/accounting/chart-of-accounts', 'sort_order' => 42],
                ['title' => 'Accounts Payable', 'slug' => 'accounts-payable', 'path' => '/accounting/accounts-payable', 'sort_order' => 43],
                ['title' => 'Accounts Receivable', 'slug' => 'accounts-receivable', 'path' => '/accounting/accounts-receivable', 'sort_order' => 44],
                ['title' => 'Bank Reconciliation', 'slug' => 'bank-reconciliation', 'path' => '/accounting/bank-reconciliation', 'sort_order' => 45],
                ['title' => 'Tax', 'slug' => 'accounting-tax', 'path' => '/accounting/tax', 'sort_order' => 46],
                ['title' => 'Fixed Assets', 'slug' => 'fixed-assets', 'path' => '/accounting/fixed-assets', 'sort_order' => 47],
            ]],
            ['title' => 'Tax Calculator', 'slug' => 'tax-calculator', 'path' => '/tax-calculator', 'icon' => 'calculator', 'sort_order' => 48],
            ['title' => 'Procurement', 'slug' => 'procurement', 'path' => '/procurement', 'icon' => 'briefcase', 'sort_order' => 49, 'children' => [
                ['title' => 'Purchase Request', 'slug' => 'procurement-purchase-request', 'path' => '/procurement/purchase-requests', 'sort_order' => 491],
                ['title' => 'RFQ & CBA', 'slug' => 'procurement-rfq-cba', 'path' => '/procurement/rfq-cba', 'sort_order' => 492],
                ['title' => 'Supplier Contract Notification', 'slug' => 'procurement-scn', 'path' => '/procurement/scn', 'sort_order' => 493],
                ['title' => 'Vendors', 'slug' => 'procurement-vendors', 'path' => '/master-data/vendors', 'sort_order' => 494],
                ['title' => 'Purchase Orders', 'slug' => 'procurement-purchase-orders', 'path' => '/procurement/purchase-requests', 'sort_order' => 495],
                ['title' => 'Goods Receipts', 'slug' => 'procurement-goods-receipts', 'path' => '/procurement/purchase-requests', 'sort_order' => 496],
                ['title' => 'Supplier Invoices', 'slug' => 'procurement-supplier-invoices', 'path' => '/procurement/purchase-requests', 'sort_order' => 497],
            ]],
            ['title' => 'HR & Administration', 'slug' => 'hr-administration', 'path' => '/administration', 'icon' => 'admin', 'sort_order' => 55, 'children' => [
                ['title' => 'Employees', 'slug' => 'hr-employees', 'path' => '/master-data/employees', 'sort_order' => 551],
                ['title' => 'Departments', 'slug' => 'hr-departments', 'path' => '/master-data/departments', 'sort_order' => 552],
                ['title' => 'Office Locations', 'slug' => 'hr-office-locations', 'path' => '/master-data/office-locations', 'sort_order' => 553],
                ['title' => 'Role Access', 'slug' => 'hr-role-access', 'path' => '/administration/role-access', 'sort_order' => 554],
                ['title' => 'Approval Matrix', 'slug' => 'hr-approval-matrix', 'path' => '/administration/approval-matrix', 'sort_order' => 555],
                ['title' => 'Audit Log', 'slug' => 'hr-audit-log', 'path' => '/administration/audit-logs', 'sort_order' => 556],
            ]],
            ['title' => 'Reports', 'slug' => 'reports', 'path' => '/reports', 'icon' => 'reports', 'sort_order' => 50, 'children' => [
                ['title' => 'Reports Summary', 'slug' => 'reports-summary', 'path' => '/reports', 'sort_order' => 501],
                ['title' => 'Expense Forecast', 'slug' => 'reports-forecast', 'path' => '/reports/forecast', 'sort_order' => 502],
                ['title' => 'Custom Report Builder', 'slug' => 'reports-custom', 'path' => '/reports/custom', 'sort_order' => 503],
            ]],
            ['title' => 'Administration', 'slug' => 'administration', 'path' => '/administration', 'icon' => 'admin', 'sort_order' => 60, 'children' => [
                ['title' => 'Master Menu', 'slug' => 'master-menu', 'path' => '/administration/master-menu', 'sort_order' => 61],
                ['title' => 'Role Access', 'slug' => 'role-access', 'path' => '/administration/role-access', 'sort_order' => 62],
                ['title' => 'Approval Matrix', 'slug' => 'administration-approval-matrix', 'path' => '/administration/approval-matrix', 'sort_order' => 63],
                ['title' => 'Audit Log', 'slug' => 'audit-log', 'path' => '/administration/audit-logs', 'sort_order' => 64],
            ]],
            ['title' => 'Settings', 'slug' => 'settings', 'path' => '/settings', 'icon' => 'settings', 'sort_order' => 70],
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
