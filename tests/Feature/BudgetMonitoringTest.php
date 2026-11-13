<?php

namespace Tests\Feature;

use App\Models\Accounting\Journal;
use App\Models\Master\BudgetCategory;
use App\Models\Master\BudgetLine;
use App\Models\Master\BankAccount;
use App\Models\Master\ChartOfAccount;
use App\Models\Master\Currency;
use App\Models\Master\Donor;
use App\Models\Master\GrantAgreement;
use App\Models\Master\GrantDisbursementRequest;
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

    public function test_budget_monitoring_exposes_the_received_grant_bank_account_and_filters_by_it(): void
    {
        [$user, $budgetLine] = $this->fixture(['budget.view']);
        $currency = Currency::create(['code' => 'IDR', 'name' => 'Rupiah', 'is_base_currency' => true, 'is_active' => true]);
        $bankGl = ChartOfAccount::create([
            'code' => '1100', 'name' => 'Grant Bank', 'account_type' => 'asset',
            'normal_balance' => 'debit', 'level' => 1, 'is_header' => false, 'is_active' => true,
        ]);
        $bank = BankAccount::create([
            'bank_name' => 'Bank Grant', 'account_number' => '987654321', 'account_name' => 'Grant Account',
            'currency_id' => $currency->id, 'gl_account_id' => $bankGl->id, 'is_active' => true,
        ]);
        $donor = Donor::create(['code' => 'DNR-BANK', 'name' => 'Donor Bank', 'type' => 'institution', 'is_active' => true]);
        $grant = GrantAgreement::create([
            'grant_no' => 'GRT-BANK-001', 'donor_id' => $donor->id, 'agreement_name' => 'Grant with received bank',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'currency_id' => $currency->id,
            'grant_value' => 1000, 'status' => 'active', 'is_active' => true,
        ]);
        $budgetLine->update(['grant_agreement_id' => $grant->id]);
        GrantDisbursementRequest::create([
            'grant_agreement_id' => $grant->id, 'request_no' => 'DR-BANK-001', 'period_label' => 'Q1 2026',
            'request_date' => '2026-01-10', 'received_date' => '2026-01-15',
            'received_bank_account_id' => $bank->id, 'status' => 'received',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/budget/monitoring?bank_account_id='.$bank->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.bank_account.id', $bank->id)
            ->assertJsonPath('data.0.bank_account.bank_name', 'Bank Grant')
            ->assertJsonPath('data.0.gl_account.code', '5100');
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
            'created_by' => $user->id,
            'unit_price' => 100,
            'quantity' => 10,
            'total_amount' => 1000,
            'gl_account_id' => $expenseAccount->id,
            'is_active' => true,
        ]);

        return [$user, $budgetLine, $expenseAccount];
    }
}
