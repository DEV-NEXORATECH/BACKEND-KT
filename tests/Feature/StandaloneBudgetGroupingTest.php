<?php

namespace Tests\Feature;

use App\Models\Master\BudgetCategory;
use App\Models\Master\BudgetLine;
use App\Models\Master\ChartOfAccount;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StandaloneBudgetGroupingTest extends TestCase
{
    use RefreshDatabase;

    public function test_standalone_budgets_are_exposed_and_filtered_by_their_own_names(): void
    {
        $role = Role::create(['name' => 'Budget Manager', 'slug' => 'budget-manager']);
        foreach (['master-data.view', 'master-data.manage', 'budget.view'] as $slug) {
            $permission = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $user = User::factory()->create(['role_id' => $role->id]);
        $category = BudgetCategory::create([
            'code' => 'CAT-STANDALONE',
            'name' => 'Standalone',
            'is_active' => true,
        ]);
        $account = ChartOfAccount::create([
            'code' => '5199',
            'name' => 'Standalone Expense',
            'account_type' => 'expense',
            'normal_balance' => 'debit',
            'level' => 1,
            'is_header' => false,
            'is_active' => true,
        ]);

        $this->actingAs($user, 'sanctum');

        foreach ([
            ['name' => 'Budget Operasional Kantor', 'code' => 'ST-001'],
            ['name' => 'Testing Lagi', 'code' => 'ST-002'],
        ] as $budget) {
            $this->postJson('/api/v1/master/budget-lines', [
                'budget_name' => $budget['name'],
                'line_code' => $budget['code'],
                'description' => $budget['name'].' item',
                'budget_category_id' => $category->id,
                'gl_account_id' => $account->id,
                'quantity' => 1,
                'unit_price' => 100,
                'total_amount' => 100,
                'period_allocations' => [['period' => 'Q1', 'amount' => 100]],
                'is_active' => true,
            ])->assertCreated()
                ->assertJsonPath('data.budget_name', $budget['name'])
                ->assertJsonPath('data.relation_no', $budget['name'])
                ->assertJsonPath('data.is_standalone', true);
        }

        $this->assertDatabaseHas('budget_lines', [
            'budget_name' => 'Testing Lagi',
            'relation_no' => null,
        ]);

        $this->getJson('/api/v1/master/budget-lines?per_page=500')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.is_standalone', true);

        $this->getJson('/api/v1/master/budget-lines?relation_no='.urlencode('Testing Lagi'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.budget_name', 'Testing Lagi')
            ->assertJsonPath('data.0.relation_no', 'Testing Lagi');

        $this->assertSame(2, BudgetLine::query()->count());
    }
$p='C:\Users\raulm\Downloads\Frontend-KT\src\modules\budgeting\pages\FundingBudgetPage.jsx'; $c=[IO.File]::ReadAllText($p); $old='          label: `B${idx + 1}`,'; $new="          label: ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'][idx],"; if(-not $c.Contains($old)){throw 'Target label tidak ditemukan'}; [IO.File]::WriteAllText($p,$c.Replace($old,$new),[Text.UTF8Encoding]::new($false))

    public function test_budget_overview_hides_orphans_and_placeholder_relation_does_not_query_them(): void
    {
        $role = Role::create(['name' => 'Budget Manager', 'slug' => 'budget-manager']);
        foreach (['master-data.view', 'budget.view'] as $slug) {
            $permission = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $user = User::factory()->create(['role_id' => $role->id]);
        $category = BudgetCategory::create([
            'code' => 'CAT-ORPHAN',
            'name' => 'Orphan',
            'is_active' => true,
        ]);

        BudgetLine::create([
            'line_code' => 'ORPHAN-001',
            'description' => 'Invalid budget without a scope',
            'budget_category_id' => $category->id,
            'quantity' => 1,
            'unit_price' => 100,
            'total_amount' => 100,
            'is_active' => true,
        ]);

        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/v1/master/budget-lines?per_page=500')
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/master/budget-lines?relation_no='.urlencode('Belum tersedia'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');
    }
}
