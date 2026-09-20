<?php

namespace Tests\Feature;

use App\Models\Accounting\Journal;
use App\Models\Master\BudgetCategory;
use App\Models\Master\BudgetLine;
use App\Models\Master\ChartOfAccount;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_budget_monitoring_uses_posted_journal_actuals(): void
    {
        [$user, $budgetLine, $expenseAccount] = $this->fixture(['budget.view']);

        $journal = Journal::create([
            'journal_number' => 'JV-BUDGET-001',
            'journal_date' => '2026-09-20',
            'journal_type' => 'manual',
            'description' => 'Posted expense',
            'status' => 'posted',
            'posted_by' => $user->id,
            'posted_at' => now(),
        ]);
        $journal->lines()->create([
            'account_id' => $expenseAccount->id,
            'budget_line_id' => $budgetLine->id,
            'debit' => 250,
            'credit' => 0,
            'line_order' => 1,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/budget/monitoring')
            ->assertOk()
            ->assertJsonPath('data.0.approved_budget', 1000)
            ->assertJsonPath('data.0.actual', 250)
            ->assertJsonPath('data.0.available', 750)
            ->assertJsonPath('totals.utilization_percent', 25);
    }

    public function test_budget_validation_blocks_amount_above_available_budget(): void
    {
        [$user, $budgetLine] = $this->fixture(['budget.validate']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/budget/validate', [
                'budget_line_id' => $budgetLine->id,
                'amount' => 1200,
            ])
            ->assertOk()
            ->assertJsonPath('data.allowed', false)
            ->assertJsonPath('data.severity', 'blocked');
    }

    private function fixture(array $permissions): array
    {
        $role = Role::create(['name' => 'Budget Role', 'slug' => 'budget-role']);
        foreach ($permissions as $permission) {
            $role->permissions()->attach(Permission::create([
                'name' => $permission,
                'slug' => $permission,
            ]));
        }

        $user = User::factory()->create(['role_id' => $role->id]);
        $category = BudgetCategory::create([
            'code' => 'CAT',
            'name' => 'Category',
            'is_active' => true,
        ]);
        $expenseAccount = ChartOfAccount::create([
            'code' => '5100',
            'name' => 'Project Expense',
            'account_type' => 'expense',
            'normal_balance' => 'debit',
            'level' => 1,
            'is_header' => false,
            'is_active' => true,
        ]);
        $budgetLine = BudgetLine::create([
            'budget_category_id' => $category->id,
            'line_code' => 'BL-001',
            'description' => 'Budget line',
            'unit_price' => 100,
            'quantity' => 10,
            'total_amount' => 1000,
            'gl_account_id' => $expenseAccount->id,
            'is_active' => true,
        ]);

        return [$user, $budgetLine, $expenseAccount];
    }
}
