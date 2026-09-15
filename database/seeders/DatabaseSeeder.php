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