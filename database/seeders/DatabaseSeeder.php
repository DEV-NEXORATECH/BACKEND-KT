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
        $role = Role::updateOrCreate(
            ['slug' => 'finance-manager'],
            ['name' => 'Finance Manager'],
        );

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

            // Master Data Granular Permissions
            ['name' => 'View Master Data', 'slug' => 'master-data.view'],
            ['name' => 'Manage Master Data', 'slug' => 'master-data.manage'],
            ['name' => 'Export Master Data', 'slug' => 'master-data.export'],
        ];

        $permissions = collect($permissionsList)->map(fn ($permission) => Permission::updateOrCreate(
            ['slug' => $permission['slug']],
            ['name' => $permission['name']],
        ));

        $role->permissions()->sync($permissions->pluck('id'));

        $menuDefinitions = [
            ['title' => 'Dashboard', 'slug' => 'dashboard', 'path' => '/dashboard', 'icon' => 'dashboard', 'sort_order' => 10],
            ['title' => 'Master Data', 'slug' => 'master-data', 'path' => '/master-data', 'icon' => 'database', 'sort_order' => 15, 'children' => [
                ['title' => 'Organizations', 'slug' => 'organizations', 'path' => '/master-data/organizations', 'sort_order' => 16],
                ['title' => 'Office Locations', 'slug' => 'office-locations', 'path' => '/master-data/office-locations', 'sort_order' => 17],
                ['title' => 'Departments', 'slug' => 'departments', 'path' => '/master-data/departments', 'sort_order' => 18],
                ['title' => 'Cost Centers', 'slug' => 'cost-centers', 'path' => '/master-data/cost-centers', 'sort_order' => 19],
                ['title' => 'Currencies', 'slug' => 'currencies', 'path' => '/master-data/currencies', 'sort_order' => 20],
                ['title' => 'Exchange Rates', 'slug' => 'exchange-rates', 'path' => '/master-data/exchange-rates', 'sort_order' => 21],
                ['title' => 'Fiscal Years', 'slug' => 'fiscal-years', 'path' => '/master-data/fiscal-years', 'sort_order' => 22],
                ['title' => 'Accounting Periods', 'slug' => 'accounting-periods', 'path' => '/master-data/accounting-periods', 'sort_order' => 23],
                ['title' => 'Chart of Accounts', 'slug' => 'chart-of-accounts', 'path' => '/master-data/chart-of-accounts', 'sort_order' => 24],
                ['title' => 'Taxes', 'slug' => 'taxes', 'path' => '/master-data/taxes', 'sort_order' => 25],
                ['title' => 'Bank Accounts', 'slug' => 'bank-accounts', 'path' => '/master-data/bank-accounts', 'sort_order' => 26],
                ['title' => 'Petty Cashes', 'slug' => 'petty-cashes', 'path' => '/master-data/petty-cashes', 'sort_order' => 27],
                ['title' => 'Payment Methods', 'slug' => 'payment-methods', 'path' => '/master-data/payment-methods', 'sort_order' => 28],
                ['title' => 'Funding Sources', 'slug' => 'funding-sources', 'path' => '/master-data/funding-sources', 'sort_order' => 29],
                ['title' => 'Donors', 'slug' => 'donors', 'path' => '/master-data/donors', 'sort_order' => 30],
                ['title' => 'Grant Agreements', 'slug' => 'grant-agreements', 'path' => '/master-data/grant-agreements', 'sort_order' => 31],
                ['title' => 'Programs', 'slug' => 'programs', 'path' => '/master-data/programs', 'sort_order' => 32],
                ['title' => 'Projects', 'slug' => 'projects', 'path' => '/master-data/projects', 'sort_order' => 33],
                ['title' => 'Activities', 'slug' => 'activities', 'path' => '/master-data/activities', 'sort_order' => 34],
                ['title' => 'Beneficiary Partners', 'slug' => 'beneficiary-partners', 'path' => '/master-data/beneficiary-partners', 'sort_order' => 35],
                ['title' => 'Reporting Dimensions', 'slug' => 'reporting-dimensions', 'path' => '/master-data/reporting-dimensions', 'sort_order' => 36],
                ['title' => 'Unit of Measures', 'slug' => 'unit-of-measures', 'path' => '/master-data/unit-of-measures', 'sort_order' => 37],
                ['title' => 'Budget Categories', 'slug' => 'budget-categories', 'path' => '/master-data/budget-categories', 'sort_order' => 38],
                ['title' => 'Budget Lines', 'slug' => 'budget-lines', 'path' => '/master-data/budget-lines', 'sort_order' => 39],
                ['title' => 'Employees', 'slug' => 'employees', 'path' => '/master-data/employees', 'sort_order' => 40],
                ['title' => 'Vendors', 'slug' => 'vendors', 'path' => '/master-data/vendors', 'sort_order' => 41],
                ['title' => 'Expense Categories', 'slug' => 'expense-categories', 'path' => '/master-data/expense-categories', 'sort_order' => 42],
                ['title' => 'Document Types', 'slug' => 'document-types', 'path' => '/master-data/document-types', 'sort_order' => 43],
                ['title' => 'Asset Categories', 'slug' => 'asset-categories', 'path' => '/master-data/asset-categories', 'sort_order' => 44],
                ['title' => 'Approval Matrices', 'slug' => 'approval-matrices', 'path' => '/master-data/approval-matrices', 'sort_order' => 45],
            ]],
            ['title' => 'Funding & Projects', 'slug' => 'funding-projects', 'path' => '/funding-projects', 'icon' => 'briefcase', 'sort_order' => 20, 'children' => [
                ['title' => 'Donor & Grant', 'slug' => 'donor-grant', 'path' => '/funding-projects/donor-grant', 'sort_order' => 21],
                ['title' => 'Program/Project', 'slug' => 'program-project', 'path' => '/funding-projects/program-project', 'sort_order' => 22],
                ['title' => 'Budget', 'slug' => 'budget', 'path' => '/funding-projects/budget', 'sort_order' => 23],
            ]],
            ['title' => 'Expenses & Approvals', 'slug' => 'expenses-approvals', 'path' => '/expenses-approvals', 'icon' => 'wallet', 'sort_order' => 30, 'children' => [
                ['title' => 'Expenses', 'slug' => 'expenses', 'path' => '/expenses-approvals/expenses', 'sort_order' => 31],
                ['title' => 'Cash Advance', 'slug' => 'cash-advance', 'path' => '/expenses-approvals/cash-advance', 'sort_order' => 32],
                ['title' => 'Reimbursement', 'slug' => 'reimbursement', 'path' => '/expenses-approvals/reimbursement', 'sort_order' => 33],
                ['title' => 'Approvals', 'slug' => 'approvals', 'path' => '/expenses-approvals/approvals', 'sort_order' => 34],
            ]],
            ['title' => 'Accounting', 'slug' => 'accounting', 'path' => '/accounting', 'icon' => 'accounting', 'sort_order' => 40, 'children' => [
                ['title' => 'Journal', 'slug' => 'journal', 'path' => '/accounting/journal', 'sort_order' => 41],
                ['title' => 'Chart of Accounts', 'slug' => 'chart-of-accounts', 'path' => '/accounting/chart-of-accounts', 'sort_order' => 42],
                ['title' => 'Accounts Payable', 'slug' => 'accounts-payable', 'path' => '/accounting/accounts-payable', 'sort_order' => 43],
                ['title' => 'Accounts Receivable', 'slug' => 'accounts-receivable', 'path' => '/accounting/accounts-receivable', 'sort_order' => 44],
                ['title' => 'Bank Reconciliation', 'slug' => 'bank-reconciliation', 'path' => '/accounting/bank-reconciliation', 'sort_order' => 45],
                ['title' => 'Tax', 'slug' => 'tax', 'path' => '/accounting/tax', 'sort_order' => 46],
            ]],
            ['title' => 'Reports', 'slug' => 'reports', 'path' => '/reports', 'icon' => 'reports', 'sort_order' => 50],
            ['title' => 'Administration', 'slug' => 'administration', 'path' => '/administration', 'icon' => 'admin', 'sort_order' => 60, 'children' => [
                ['title' => 'Master Menu', 'slug' => 'master-menu', 'path' => '/administration/master-menu', 'sort_order' => 61],
                ['title' => 'Role Access', 'slug' => 'role-access', 'path' => '/administration/role-access', 'sort_order' => 62],
            ]],
            ['title' => 'Settings', 'slug' => 'settings', 'path' => '/settings', 'icon' => 'settings', 'sort_order' => 70],
        ];

        $menus = collect();

        foreach ($menuDefinitions as $definition) {
            $children = $definition['children'] ?? [];
            unset($definition['children']);

            $menu = Menu::updateOrCreate(
                ['slug' => $definition['slug']],
                [...$definition, 'parent_id' => null, 'is_active' => true],
            );

            $menus->push($menu);

            foreach ($children as $childDefinition) {
                $menus->push(Menu::updateOrCreate(
                    ['slug' => $childDefinition['slug']],
                    [...$childDefinition, 'parent_id' => $menu->id, 'is_active' => true],
                ));
            }
        }

        $role->menus()->sync($menus->pluck('id'));

        User::updateOrCreate(
            ['email' => 'admin@kaoemtelapak.test'],
            [
                'name' => 'Admin Kaoem Telapak',
                'role_id' => $role->id,
                'password' => Hash::make('password123'),
            ],
        );

        // Run Master Data Seeder
        $this->call(MasterDataSeeder::class);
    }
}