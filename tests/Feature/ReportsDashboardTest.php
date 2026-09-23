<?php

namespace Tests\Feature;

use App\Models\Accounting\Journal;
use App\Models\Master\BankAccount;
use App\Models\Master\BudgetCategory;
use App\Models\Master\BudgetLine;
use App\Models\Master\ChartOfAccount;
use App\Models\Master\Currency;
use App\Models\Permission;
use App\Models\Procurement\SupplierInvoice;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_and_reports_use_real_posted_data(): void
    {
        [$user, $budgetLine, $expenseAccount, $liabilityAccount, $bank] = $this->fixture();

        $journal = Journal::create([
            'journal_number' => 'JV-RPT-001',
            'journal_date' => '2026-09-20',
            'journal_type' => 'manual',
            'reference' => 'REAL-POSTED',
            'description' => 'Real posted project expense',
            'status' => 'posted',
            'posted_by' => $user->id,
            'posted_at' => now(),
        ]);
        $journal->lines()->create(['account_id' => $expenseAccount->id, 'budget_line_id' => $budgetLine->id, 'debit' => 300, 'credit' => 0, 'line_order' => 1]);
        $journal->lines()->create(['account_id' => $liabilityAccount->id, 'budget_line_id' => $budgetLine->id, 'debit' => 0, 'credit' => 300, 'line_order' => 2]);

        SupplierInvoice::create([
            'invoice_number' => 'INV-RPT-001',
            'invoice_date' => '2026-09-20',
            'due_date' => '2026-09-21',
            'status' => 'posted',
            'match_status' => 'matched',
            'total_amount' => 300,
            'paid_amount' => 100,
        ]);

        $bank->transactions()->create([
            'transaction_date' => '2026-09-22',
            'reference' => 'BNK-RPT-001',
            'description' => 'Payment',
            'debit' => 0,
            'credit' => 100,
            'status' => 'matched',
        ]);

        $dashboardResponse = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/dashboard/overview?end_date=2026-09-30');

        $dashboardResponse->assertOk()
            ->assertJsonPath('summary.approved_budget', 1000)
            ->assertJsonPath('summary.actual_expense', 300)
            ->assertJsonPath('summary.open_ap', 200)
            ->assertJsonPath('summary.operating_cash', -100)
            ->assertJsonPath('recent_transactions.0.reference', 'REAL-POSTED');

        $this->getJson('/api/v1/reports/summary?end_date=2026-09-30')
            ->assertOk()
            ->assertJsonPath('budget_vs_actual.totals.actual', 300)
            ->assertJsonPath('financial_statement.totals_by_type.expense', 300)
            ->assertJsonPath('financial_statement.totals_by_type.liability', 300)
            ->assertJsonPath('ap_aging.buckets.1_30', 200)
            ->assertJsonPath('cash_bank.total_balance', -100);
    }

    private function fixture(): array
    {
        $role = Role::create(['name' => 'Finance', 'slug' => 'finance']);
        foreach (['dashboard.view', 'reports.view'] as $permission) {
            $role->permissions()->attach(Permission::create(['name' => $permission, 'slug' => $permission]));
        }
        $user = User::factory()->create(['role_id' => $role->id]);

        $currency = Currency::create(['code' => 'IDR', 'name' => 'Rupiah', 'is_base_currency' => true, 'is_active' => true]);
        $cashAccount = ChartOfAccount::create(['code' => '1000', 'name' => 'Bank', 'account_type' => 'asset', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        $liabilityAccount = ChartOfAccount::create(['code' => '2000', 'name' => 'Accounts Payable', 'account_type' => 'liability', 'normal_balance' => 'credit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        $expenseAccount = ChartOfAccount::create(['code' => '5100', 'name' => 'Program Expense', 'account_type' => 'expense', 'normal_balance' => 'debit', 'level' => 1, 'is_header' => false, 'is_active' => true]);
        $bank = BankAccount::create(['bank_name' => 'Test Bank', 'account_number' => '001', 'account_name' => 'Main', 'currency_id' => $currency->id, 'gl_account_id' => $cashAccount->id, 'is_active' => true]);
        $category = BudgetCategory::create(['code' => 'OPS', 'name' => 'Operations', 'is_active' => true]);
        $budgetLine = BudgetLine::create(['budget_category_id' => $category->id, 'line_code' => 'OPS-001', 'description' => 'Ops', 'total_amount' => 1000, 'gl_account_id' => $expenseAccount->id, 'is_active' => true]);

        return [$user, $budgetLine, $expenseAccount, $liabilityAccount, $bank];
    }
}
