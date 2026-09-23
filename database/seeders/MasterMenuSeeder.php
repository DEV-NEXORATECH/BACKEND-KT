<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Role;
use Illuminate\Database\Seeder;

class MasterMenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Keep this standalone seeder aligned with DatabaseSeeder. These are
        // the only categories that should be visible below Master Data.
        $masterMenu = Menu::updateOrCreate(['slug' => 'master-data'], [
            'title' => 'Master Data',
            'path' => '/master-data',
            'icon' => 'database',
            'sort_order' => 15,
            'is_active' => true,
            'parent_id' => null,
        ]);

        $groups = [
            ['title' => 'Organization & Structure', 'slug' => 'master-organization-structure', 'sort_order' => 16, 'children' => [
                ['title' => 'Organizations', 'slug' => 'master-organizations', 'path' => '/master-data/organizations', 'sort_order' => 161],
                ['title' => 'Office Locations', 'slug' => 'master-office-locations', 'path' => '/master-data/office-locations', 'sort_order' => 162],
                ['title' => 'Departments', 'slug' => 'master-departments', 'path' => '/master-data/departments', 'sort_order' => 163],
                ['title' => 'Positions', 'slug' => 'master-positions', 'path' => '/master-data/positions', 'sort_order' => 165],
                ['title' => 'Staff / Employees', 'slug' => 'master-employees', 'path' => '/master-data/employees', 'sort_order' => 166],
            ]],
            ['title' => 'Finance & Accounting', 'slug' => 'master-finance-accounting', 'sort_order' => 17, 'children' => [
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
            ['title' => 'Funding & Projects', 'slug' => 'master-funding-projects', 'sort_order' => 18, 'children' => [
                ['title' => 'Donors', 'slug' => 'master-donors', 'path' => '/master-data/donors', 'sort_order' => 182],
                ['title' => 'Grant/Agreements', 'slug' => 'master-grant-agreements', 'path' => '/master-data/grant-agreements', 'sort_order' => 183],
                ['title' => 'Programs', 'slug' => 'master-programs', 'path' => '/master-data/programs', 'sort_order' => 184],
                ['title' => 'Projects', 'slug' => 'master-projects', 'path' => '/master-data/projects', 'sort_order' => 185],
                ['title' => 'Activities', 'slug' => 'master-activities', 'path' => '/master-data/activities', 'sort_order' => 186],
                ['title' => 'Budget Codes', 'slug' => 'master-budget-codes', 'path' => '/master-data/budget-lines', 'sort_order' => 187],
                ['title' => 'Sources of Fund (SoF)', 'slug' => 'master-sof', 'path' => '/master-data/funding-sources', 'sort_order' => 188],
            ]],
            ['title' => 'Budget & Reporting', 'slug' => 'master-budget-reporting', 'sort_order' => 19, 'children' => [
                ['title' => 'Budget Categories', 'slug' => 'master-budget-categories', 'path' => '/master-data/budget-categories', 'sort_order' => 193],
                ['title' => 'Budget Templates / Lines', 'slug' => 'master-budget-templates', 'path' => '/master-data/budget-lines', 'sort_order' => 194],
                ['title' => 'Reporting Categories', 'slug' => 'master-reporting-categories', 'path' => '/master-data/reporting-categories', 'sort_order' => 195],
                ['title' => 'Reporting Dimensions', 'slug' => 'master-reporting-dimensions', 'path' => '/master-data/reporting-dimensions', 'sort_order' => 191],
            ]],
            ['title' => 'Expenses & Assets', 'slug' => 'master-expenses-assets', 'sort_order' => 20, 'children' => [
                ['title' => 'Expense Categories', 'slug' => 'master-expense-categories', 'path' => '/master-data/expense-categories', 'sort_order' => 201],
                ['title' => 'Document / Expense Types', 'slug' => 'master-expense-types', 'path' => '/master-data/document-types', 'sort_order' => 202],
                ['title' => 'Asset Categories', 'slug' => 'master-asset-categories', 'path' => '/master-data/asset-categories', 'sort_order' => 203],
            ]],
            ['title' => 'Procurement', 'slug' => 'master-procurement', 'sort_order' => 21, 'children' => [
                ['title' => 'Vendors/Suppliers/Consultants', 'slug' => 'master-vendors', 'path' => '/master-data/vendors', 'sort_order' => 211],
                ['title' => 'Vendor Categories', 'slug' => 'master-vendor-categories', 'path' => '/master-data/vendor-categories', 'sort_order' => 212],
                ['title' => 'Items/Services', 'slug' => 'master-items-services', 'path' => '/master-data/procurement-items', 'sort_order' => 213],
                ['title' => 'Unit of Measures', 'slug' => 'master-unit-of-measures', 'path' => '/master-data/unit-of-measures', 'sort_order' => 214],
                ['title' => 'Procurement Categories', 'slug' => 'master-procurement-categories', 'path' => '/master-data/procurement-categories', 'sort_order' => 215],
            ]],
        ];

        foreach ($groups as $group) {
            $groupMenu = Menu::updateOrCreate(['slug' => $group['slug']], [
                'parent_id' => $masterMenu->id,
                'title' => $group['title'],
                'path' => '/master-data',
                'sort_order' => $group['sort_order'],
                'is_active' => true,
            ]);
            foreach ($group['children'] as $child) {
                Menu::updateOrCreate(['slug' => $child['slug']], [...$child, 'parent_id' => $groupMenu->id, 'is_active' => true]);
            }
        }

        Menu::whereIn('slug', ['coa', 'fiscal', 'currency', 'tax', 'master-fund-grant-management', 'master-ca', 'master-depreciation', 'master-cost-centers', 'master-petty-cashes', 'master-beneficiary-partners'])
            ->update(['is_active' => false]);

        $roles = Role::all();
        $menuIds = Menu::where('is_active', true)->pluck('id')->toArray();
        foreach ($roles as $role) {
            $role->menus()->syncWithoutDetaching($menuIds);
        }
    }
}
