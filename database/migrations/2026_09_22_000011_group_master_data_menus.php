<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $masterId = DB::table('menus')->where('slug', 'master-data')->value('id');
        if (! $masterId) return;
        $groups = [
            ['title' => 'Organization & Structure', 'slug' => 'master-organization-structure', 'sort_order' => 16, 'children' => ['master-organizations', 'master-office-locations', 'master-departments', 'master-cost-centers', 'master-employees']],
            ['title' => 'Finance & Accounting', 'slug' => 'master-finance-accounting', 'sort_order' => 17, 'children' => ['master-currencies', 'master-exchange-rates', 'master-fiscal-years', 'master-accounting-periods', 'master-chart-of-accounts', 'master-taxes', 'master-bank-accounts', 'master-petty-cashes', 'master-payment-methods']],
            ['title' => 'Funding & Projects', 'slug' => 'master-funding-projects', 'sort_order' => 18, 'children' => ['master-funding-sources', 'master-donors', 'master-grant-agreements', 'master-programs', 'master-projects', 'master-activities', 'master-beneficiary-partners']],
            ['title' => 'Budget & Reporting', 'slug' => 'master-budget-reporting', 'sort_order' => 19, 'children' => ['master-reporting-dimensions', 'master-unit-of-measures', 'master-budget-categories', 'master-budget-lines']],
            ['title' => 'Expenses & Assets', 'slug' => 'master-expenses-assets', 'sort_order' => 20, 'children' => ['master-expense-categories', 'master-document-types', 'master-asset-categories']],
        ];
        foreach ($groups as $group) {
            $groupId = DB::table('menus')->where('slug', $group['slug'])->value('id');
            $values = ['title' => $group['title'], 'path' => '/master-data', 'icon' => null, 'sort_order' => $group['sort_order'], 'parent_id' => $masterId, 'is_active' => true, 'updated_at' => now()];
            if ($groupId) DB::table('menus')->where('id', $groupId)->update($values);
            else $groupId = DB::table('menus')->insertGetId($values + ['slug' => $group['slug'], 'created_at' => now()]);
            DB::table('menus')->whereIn('slug', $group['children'])->update(['parent_id' => $groupId, 'is_active' => true, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $groups = ['master-organization-structure', 'master-finance-accounting', 'master-funding-projects', 'master-budget-reporting', 'master-expenses-assets'];
        $masterId = DB::table('menus')->where('slug', 'master-data')->value('id');
        if ($masterId) DB::table('menus')->whereIn('slug', $groups)->delete();
    }
};
