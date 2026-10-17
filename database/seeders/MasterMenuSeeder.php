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
        // The Programs & Projects group is intentionally nested under Donor & Grant.
        // Deactivate the legacy parent so old seeded menus do not remain visible.
        Menu::whereIn('slug', [
            'master-programs-projects', 'master-programs', 'master-funding-sources',
            'master-donor-types', 'master-projects-activities', 'master-project-workplan',
            'master-grant-agreements', 'master-project-frameworks', 'master-project-timeline-workplan',
            'master-expenses-assets',
        ])->update(['is_active' => false]);

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
            ['title' => 'Finance & Accounting', 'slug' => 'master-finance-accounting', 'sort_order' => 17, 'children' => [
                ['title' => 'Bank Accounts', 'slug' => 'master-bank-accounts', 'path' => '/master-data/bank-accounts', 'sort_order' => 177],
                ['title' => 'Payment Methods', 'slug' => 'master-payment-methods', 'path' => '/master-data/payment-methods', 'sort_order' => 179],
                ['title' => 'Fx Rates', 'slug' => 'master-fx-rates', 'path' => '/master-data/exchange-rates', 'sort_order' => 172],
                ['title' => 'Currencies', 'slug' => 'master-currencies', 'path' => '/master-data/currencies', 'sort_order' => 171],
                ['title' => 'Fiscal Years', 'slug' => 'master-fiscal-years', 'path' => '/master-data/fiscal-years', 'sort_order' => 173],
                ['title' => 'Accounting Periods', 'slug' => 'master-accounting-periods', 'path' => '/master-data/accounting-periods', 'sort_order' => 174],
                ['title' => 'Tax Master', 'slug' => 'master-taxes', 'path' => '/master-data/taxes', 'sort_order' => 176],
                ['title' => 'Members / Anggota', 'slug' => 'master-customers', 'path' => '/master-data/customers', 'sort_order' => 178],
                ['title' => 'Iuran Anggota', 'slug' => 'master-membership-dues', 'path' => '/master-data/membership-dues', 'sort_order' => 181],
            ]],
            ['title' => 'Donor & Grant', 'slug' => 'master-funding-projects', 'path' => '/master-data/donor-grant-integrated', 'sort_order' => 18, 'children' => [
                ['title' => 'Donor & Grant Terintegrasi', 'slug' => 'master-donor-grant-integrated', 'path' => '/master-data/donor-grant-integrated', 'sort_order' => 180],
            ]],
            ['title' => 'Budget & Forecast', 'slug' => 'master-budget-reporting', 'sort_order' => 19, 'children' => [
                ['title' => 'Budget Categories', 'slug' => 'master-budget-categories', 'path' => '/master-data/budget-categories', 'sort_order' => 193],
                ['title' => 'Budget Categories & Templates', 'slug' => 'master-budget-templates', 'path' => '/master-data/budget-lines', 'sort_order' => 194],
                ['title' => 'Reporting Categories', 'slug' => 'master-reporting-categories', 'path' => '/master-data/reporting-categories', 'sort_order' => 195],
                ['title' => 'Reporting Dimensions', 'slug' => 'master-reporting-dimensions', 'path' => '/master-data/reporting-dimensions', 'sort_order' => 191],
            ]],
            ['title' => 'Expenses', 'slug' => 'master-expenses', 'sort_order' => 20, 'children' => [
                ['title' => 'Expense Categories', 'slug' => 'master-expense-categories', 'path' => '/master-data/expense-categories', 'sort_order' => 201],
                ['title' => 'Document Types', 'slug' => 'master-expense-types', 'path' => '/master-data/document-types', 'sort_order' => 202],
            ]],
            ['title' => 'Assets', 'slug' => 'master-assets', 'sort_order' => 21, 'children' => [
                ['title' => 'Asset Categories', 'slug' => 'master-asset-categories', 'path' => '/master-data/asset-categories', 'sort_order' => 203],
            ]],
            ['title' => 'Procurement', 'slug' => 'master-procurement', 'sort_order' => 22, 'children' => [
                ['title' => 'Suppliers / Vendors', 'slug' => 'master-vendors', 'path' => '/master-data/vendors', 'sort_order' => 210],
                ['title' => 'Vendor Categories', 'slug' => 'master-vendor-categories', 'path' => '/master-data/vendor-categories', 'sort_order' => 211],
                ['title' => 'Items / Services', 'slug' => 'master-items-services', 'path' => '/master-data/procurement-items', 'sort_order' => 212],
                ['title' => 'Unit of Measures', 'slug' => 'master-unit-of-measures', 'path' => '/master-data/unit-of-measures', 'sort_order' => 213],
                ['title' => 'Procurement Categories', 'slug' => 'master-procurement-categories', 'path' => '/master-data/procurement-categories', 'sort_order' => 214],
            ]],
        ];

        foreach ($groups as $group) {
            $groupMenu = Menu::updateOrCreate(['slug' => $group['slug']], [
                'parent_id' => $masterMenu->id,
                'title' => $group['title'],
                'path' => $group['path'] ?? '/master-data',
                'sort_order' => $group['sort_order'],
                'is_active' => true,
            ]);
            foreach ($group['children'] as $child) {
                Menu::updateOrCreate(['slug' => $child['slug']], [...$child, 'parent_id' => $groupMenu->id, 'is_active' => true]);
            }
        }

        // Programs and projects are presented under Donor & Grant in the UI.
        // Their routes and database relationships remain unchanged.
        Menu::whereIn('slug', [
            'master-programs-projects',
            'master-donors',
            'master-grant-agreements',
            'master-programs',
            'master-funding-sources',
            'master-donor-types',
            'master-projects',
            'master-activities',
        ])->update(['is_active' => false]);

        // Donor and grant records are now created and viewed through the
        // single integrated master page. Disable legacy duplicate entries.
        Menu::whereIn('slug', ['master-donors', 'master-grant-agreements', 'master-budget-codes', 'master-sof'])
            ->update(['is_active' => false]);

        // Keep the functional Donor & Grant navigation in sync when this
        // standalone menu seeder is run without RoleSeeder first.
        $donorGrantMenu = Menu::where('slug', 'donor-grant')->first();
        if ($donorGrantMenu) {
            Menu::whereIn('slug', ['donor-grant-integrated-record', 'donor-grant-donors'])->update(['is_active' => false]);
            Menu::updateOrCreate(['slug' => 'donor-grant-project-frameworks'], [
                'parent_id' => $donorGrantMenu->id,
                'title' => 'Project Frameworks',
                'path' => '/project-frameworks',
                'sort_order' => 23,
                'is_active' => true,
            ]);
        }

        // Project Frameworks belongs only under the functional Donor & Grant
        // menu, not under the Master Data group.
        // Project Frameworks is now also exposed under Master Data so users
        // can open it directly from Programs & Projects / Donor & Grant.

        Menu::whereIn('slug', ['coa', 'fiscal', 'currency', 'tax', 'master-fund-grant-management', 'master-ca', 'master-depreciation', 'master-cost-centers', 'master-petty-cashes', 'master-beneficiary-partners', 'master-projects', 'master-activities'])
            ->update(['is_active' => false]);

        $roles = Role::all();
        $menuIds = Menu::where('is_active', true)->pluck('id')->toArray();
        foreach ($roles as $role) {
            $role->menus()->syncWithoutDetaching($menuIds);
        }
    }
}
